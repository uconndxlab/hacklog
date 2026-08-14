<?php

namespace App\Services;

use App\AI\IntakeAiProvider;
use App\AI\OllamaIntakeProvider;
use App\AI\OpenAiIntakeProvider;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class ProjectIntakeService
{
    /**
     * Analyze submitted text against a project's context and return proposed tasks.
     *
     * Delegates inference to the configured AI provider (Ollama or OpenAI).
     * Called by ProcessProjectIntakeJob after the intake record is persisted.
     *
     * @return array{ok: bool, provider?: string, model?: string, summary?: string, proposals?: array<int, array<string, mixed>>, error?: string}
     */
    public function analyze(Project $project, string $inputText): array
    {
        $provider = $this->resolveProvider();

        $systemContent = $this->buildSystemMessage($project);
        $userContent   = "Identify every actionable work item in the following content and return them as proposed tasks for this project.\n\n---\n{$inputText}\n---";

        $result = $provider->analyze($systemContent, $userContent);

        if (!$result['ok']) {
            return $result;
        }

        $proposals = $this->normalizeProposals($result['proposals'] ?? [], $project);

        // Validate possible_duplicate_of values against actual project task titles.
        // This prevents the model from fabricating task titles not in the existing-tasks list.
        $openTaskTitles = Task::withoutGlobalScope('ordered')
            ->whereHas('column', fn ($q) => $q->where('project_id', $project->id))
            ->where('status', '!=', 'completed')
            ->pluck('title')
            ->all();

        foreach ($proposals as &$proposal) {
            if ($proposal['possible_duplicate_of'] !== null
                && !in_array($proposal['possible_duplicate_of'], $openTaskTitles, true)) {
                $proposal['possible_duplicate_of'] = null;
            }
        }
        unset($proposal);

        return [
            'ok'        => true,
            'provider'  => $result['provider'] ?? null,
            'model'     => $result['model'] ?? null,
            'summary'   => $result['summary'] ?? '',
            'proposals' => $proposals,
        ];
    }

    /**
     * Resolve the configured AI provider.
     */
    private function resolveProvider(): IntakeAiProvider
    {
        return match (config('ai.provider', 'ollama')) {
            'openai' => new OpenAiIntakeProvider(),
            default  => new OllamaIntakeProvider(),
        };
    }

    /**
     * Assemble a compact project context for the system message.
     */
    private function buildSystemMessage(Project $project): string
    {
        $lines = [];
        $lines[] = 'You are Hacklog AI, an intake assistant for the Hacklog project management application.';
        $lines[] = 'Your task: analyze submitted text and identify actionable work items for the project below.';
        $lines[] = '';
        $lines[] = 'PROJECT CONTEXT:';
        $lines[] = 'Project: ' . $project->name . ' (' . $project->status . ')';

        if ($project->description) {
            $plain = mb_substr(trim(strip_tags((string) $project->description)), 0, 200);
            if ($plain !== '') {
                $lines[] = 'Description: ' . $plain;
            }
        }

        // Phases — active and planned only, up to 10
        $phases = $project->phases()
            ->whereIn('status', ['planned', 'active'])
            ->orderByRaw('CASE WHEN status = "active" THEN 1 ELSE 2 END')
            ->orderBy('end_date', 'asc')
            ->limit(10)
            ->get(['id', 'name', 'status', 'end_date']);

        if ($phases->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Phases (use the ID for suggested_phase_id when relevant):';
            foreach ($phases as $phase) {
                $entry = '  [' . $phase->id . '] ' . $phase->name . ' (' . $phase->status . ')';
                if ($phase->end_date) {
                    $entry .= ', due ' . $phase->end_date->format('Y-m-d');
                }
                $lines[] = $entry;
            }
        } else {
            $lines[] = 'Phases: none defined.';
        }

        // Team members — people who have tasks on this project, or active non-client users
        $teamMembers = User::where('active', true)
            ->whereHas('tasks', function ($q) use ($project) {
                $q->whereHas('column', fn ($q) => $q->where('project_id', $project->id));
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        if ($teamMembers->isEmpty()) {
            $teamMembers = User::where('active', true)
                ->where('role', '!=', 'client')
                ->orderBy('name')
                ->limit(15)
                ->get(['id', 'name']);
        }

        if ($teamMembers->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Team members (for project context only):';
            foreach ($teamMembers as $member) {
                $lines[] = '  ' . $member->name;
            }
        }

        // Existing open tasks — titles only, for duplicate awareness
        $openTasks = Task::withoutGlobalScope('ordered')
            ->whereHas('column', fn ($q) => $q->where('project_id', $project->id))
            ->where('status', '!=', 'completed')
            ->orderBy('updated_at', 'desc')
            ->limit(20)
            ->pluck('title');

        if ($openTasks->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Existing open tasks (for duplicate awareness — use possible_duplicate_of if similar):';
            foreach ($openTasks as $title) {
                $lines[] = '  - ' . $title;
            }
        }

        $lines[] = '';
        $lines[] = 'INSTRUCTIONS:';
        $lines[] = '- Identify work items that are requested, implied, or reasonably inferred from the text. Include both explicit requests and clear implicit action items.';
        $lines[] = '- Only set due_date (YYYY-MM-DD) when the text states an explicit deadline.';
        $lines[] = '- Only set suggested_phase_id when the context reasonably implies a specific phase.';
        $lines[] = '- Set confidence (0.0 to 1.0) to reflect how directly the task is supported by the text. Use 0.7+ for clear requests, 0.5-0.7 for inferred items.';
        $lines[] = '- Set possible_duplicate_of to the exact title of a matching task copied verbatim from the existing tasks list above, but ONLY when the proposed task and the existing task address the same specific action or deliverable — not merely a shared topic, category, or theme. Leave it empty when in doubt.';
        $lines[] = '- Keep descriptions brief and factual. source_excerpt should be a short quote from the submitted text.';
        $lines[] = '- If nothing actionable is found, return zero proposals. Otherwise, be thorough.';

        return implode("\n", $lines);
    }

    /**
     * JSON Schema for Ollama structured output (constrained generation).
    /**
     * Normalize and security-validate raw proposals from the model.
     *
     * Strips phase/assignee IDs that do not exist in the database to prevent
     * the model from referencing arbitrary IDs.
     *
     * @param array<mixed> $rawProposals
     * @return array<int, array<string, mixed>>
     */
    private function normalizeProposals(array $rawProposals, Project $project): array
    {
        $validPhaseIds = $project->phases()->pluck('id')->flip();

        $normalized = [];

        foreach ($rawProposals as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $title = trim((string) ($raw['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            // Validate phase ID against actual project phases
            $phaseId = null;
            if (isset($raw['suggested_phase_id']) && is_numeric($raw['suggested_phase_id'])) {
                $candidate = (int) $raw['suggested_phase_id'];
                if ($validPhaseIds->has($candidate)) {
                    $phaseId = $candidate;
                }
            }

            // Assignee is never set by the model — always null; UI uses defaultAssigneeId.

            // Validate date format
            $dueDate = null;
            if (!empty($raw['due_date']) && is_string($raw['due_date'])) {
                try {
                    $d = \Carbon\Carbon::createFromFormat('Y-m-d', trim($raw['due_date']));
                    if ($d && $d->format('Y-m-d') === trim($raw['due_date'])) {
                        $dueDate = trim($raw['due_date']);
                    }
                } catch (\Throwable) {
                    // invalid date — discard
                }
            }

            $confidence = null;
            if (isset($raw['confidence']) && is_numeric($raw['confidence'])) {
                $confidence = round(max(0.0, min(1.0, (float) $raw['confidence'])), 2);
            }

            $description = isset($raw['description']) ? trim((string) $raw['description']) : null;
            $sourceExcerpt = isset($raw['source_excerpt']) ? trim((string) $raw['source_excerpt']) : null;

            // Sanitize possible_duplicate_of: take only the first line and cap length.
            // The model sometimes writes reasoning text here instead of a bare task title.
            $possibleDuplicate = null;
            if (!empty($raw['possible_duplicate_of'])) {
                $raw_dup = trim((string) $raw['possible_duplicate_of']);
                // Strip after first newline (chain-of-thought continues on subsequent lines)
                $firstLine = strtok($raw_dup, "\n");
                $raw_dup = $firstLine !== false ? trim($firstLine) : $raw_dup;
                // If it's longer than a plausible task title, discard it
                if ($raw_dup !== '' && mb_strlen($raw_dup) <= 255) {
                    $possibleDuplicate = $raw_dup;
                }
            }

            $normalized[] = [
                'title' => $title,
                'description' => $description !== '' ? $description : null,
                'suggested_phase_id' => $phaseId,
                'suggested_assignee_id' => null,
                'due_date' => $dueDate,
                'confidence' => $confidence,
                'source_excerpt' => $sourceExcerpt !== '' ? $sourceExcerpt : null,
                'possible_duplicate_of' => $possibleDuplicate !== '' ? $possibleDuplicate : null,
            ];
        }

        return $normalized;
    }
}
