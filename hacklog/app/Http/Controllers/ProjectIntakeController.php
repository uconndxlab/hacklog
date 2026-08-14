<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessProjectIntakeJob;
use App\Models\Project;
use App\Models\ProjectIntake;
use App\Models\ProjectIntakeProposal;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectSlackNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectIntakeController extends Controller
{
    protected function authorizeAccess(Project $project): void
    {
        $user = auth()->user();
        if (!$user || $user->isClient()) {
            abort(403, 'You are not authorized to use AI Intake.');
        }
    }

    protected function resolveIntake(Project $project, ProjectIntake $intake): void
    {
        if ($intake->project_id !== $project->id) {
            abort(404);
        }
    }

    public function index(Project $project)
    {
        $this->authorizeAccess($project);

        $recentIntakes = ProjectIntake::where('project_id', $project->id)
            ->with('user')
            ->withCount([
                'proposals',
                'proposals as pending_count'   => fn ($q) => $q->where('status', ProjectIntakeProposal::STATUS_PENDING),
                'proposals as approved_count'  => fn ($q) => $q->where('status', ProjectIntakeProposal::STATUS_APPROVED),
                'proposals as dismissed_count' => fn ($q) => $q->where('status', ProjectIntakeProposal::STATUS_DISMISSED),
            ])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('projects.intake', compact('project', 'recentIntakes'));
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorizeAccess($project);

        $validated = $request->validate([
            'input_text' => 'required|string|min:10|max:38000',
        ]);

        $correlationId = (string) Str::uuid();

        $intake = ProjectIntake::create([
            'project_id'     => $project->id,
            'user_id'        => $request->user()->id,
            'source_type'    => ProjectIntake::SOURCE_TYPE_MANUAL,
            'source_content' => $validated['input_text'],
            'status'         => ProjectIntake::STATUS_QUEUED,
            'correlation_id' => $correlationId,
        ]);

        ProcessProjectIntakeJob::dispatch($intake->id);

        Log::info('Hacklog AI: intake created', [
            'intake_id'      => $intake->id,
            'project_id'     => $project->id,
            'correlation_id' => $correlationId,
            'input_length'   => strlen($validated['input_text']),
        ]);

        return redirect()->route('projects.intake.show', [$project, $intake]);
    }

    public function show(Project $project, ProjectIntake $intake)
    {
        $this->authorizeAccess($project);
        $this->resolveIntake($project, $intake);

        $intake->load([
            'user',
            'proposals.suggestedPhase',
            'proposals.suggestedAssignee',
            'proposals.createdTask',
        ]);

        [$phases, $users] = $this->loadFormData($project);

        // Default assignee: the team member with the most tasks on this project (open or completed).
        // Uses all tasks so projects where everything is done still get a sensible default.
        $defaultAssigneeId = \Illuminate\Support\Facades\DB::table('tasks')
            ->join('task_user', 'tasks.id', '=', 'task_user.task_id')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->where('columns.project_id', $project->id)
            ->selectRaw('task_user.user_id, COUNT(tasks.id) as task_count')
            ->groupBy('task_user.user_id')
            ->orderByDesc('task_count')
            ->limit(1)
            ->value('user_id');

        // Build a title → Task map so the view can link duplicate warnings to the real task.
        $dupTitles = $intake->proposals->whereNotNull('possible_duplicate_of')->pluck('possible_duplicate_of')->unique();
        $duplicateTasks = \App\Models\Task::withoutGlobalScope('ordered')
            ->whereHas('column', fn ($q) => $q->where('project_id', $project->id))
            ->whereIn('title', $dupTitles->all())
            ->get(['id', 'title'])
            ->keyBy('title');

        return view('projects.intake-show', compact('project', 'intake', 'phases', 'users', 'defaultAssigneeId', 'duplicateTasks'));
    }

    public function status(Project $project, ProjectIntake $intake): JsonResponse
    {
        $this->authorizeAccess($project);
        $this->resolveIntake($project, $intake);

        return response()->json([
            'status'         => $intake->status,
            'proposal_count' => $intake->status === ProjectIntake::STATUS_READY
                ? $intake->proposals()->count()
                : null,
        ]);
    }

    public function approveProposal(
        Request $request,
        Project $project,
        ProjectIntake $intake,
        ProjectIntakeProposal $proposal
    ): RedirectResponse {
        $this->authorizeAccess($project);
        $this->resolveIntake($project, $intake);

        if ($proposal->project_intake_id !== $intake->id) {
            abort(404);
        }

        if (!$proposal->isPending()) {
            return redirect()->route('projects.intake.show', [$project, $intake])
                ->with('error', 'This proposal has already been reviewed.');
        }

        $validPhaseIds = $project->phases()->pluck('id');

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'phase_id'    => ['nullable', 'integer', Rule::in($validPhaseIds)],
            'assignee_id' => 'nullable|integer|exists:users,id',
            'due_date'    => 'nullable|date',
        ]);

        $column = $project->columns()->where('is_default', true)->first()
            ?? $project->columns()->orderBy('position')->first();

        if (!$column) {
            return redirect()->route('projects.intake.show', [$project, $intake])
                ->with('error', 'This project has no kanban columns. Set up the board first.');
        }

        $task = Task::create([
            'column_id'   => $column->id,
            'phase_id'    => $validated['phase_id'] ?? null,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status'      => 'planned',
            'position'    => Task::getNextPositionInColumn($column->id),
            'due_date'    => $validated['due_date'] ?? null,
            'created_by'  => auth()->id(),
            'updated_by'  => auth()->id(),
        ]);

        if (!empty($validated['assignee_id'])) {
            $task->users()->sync([$validated['assignee_id']]);
        }

        $proposal->update([
            'status'          => ProjectIntakeProposal::STATUS_APPROVED,
            'created_task_id' => $task->id,
        ]);

        // Notify project Slack channel — failure must not prevent the redirect.
        app(ProjectSlackNotificationService::class)
            ->notifyIntakeApproval($project, $intake, [$task->title]);

        return redirect()->route('projects.intake.show', [$project, $intake])
            ->with('success', "Task \"{$task->title}\" added to {$project->name}.");
    }

    public function bulkApproveProposals(
        Request $request,
        Project $project,
        ProjectIntake $intake
    ): RedirectResponse {
        $this->authorizeAccess($project);
        $this->resolveIntake($project, $intake);

        $validated = $request->validate([
            'proposal_ids'   => 'required|array|min:1|max:25',
            'proposal_ids.*' => 'integer|exists:project_intake_proposals,id',
        ]);

        $column = $project->columns()->where('is_default', true)->first()
            ?? $project->columns()->orderBy('position')->first();

        if (!$column) {
            return redirect()->route('projects.intake.show', [$project, $intake])
                ->with('error', 'This project has no kanban columns. Set up the board first.');
        }

        $proposals = ProjectIntakeProposal::whereIn('id', $validated['proposal_ids'])
            ->where('project_intake_id', $intake->id)
            ->where('status', ProjectIntakeProposal::STATUS_PENDING)
            ->get();

        $created = 0;

        foreach ($proposals as $proposal) {
            $task = Task::create([
                'column_id'   => $column->id,
                'phase_id'    => $proposal->suggested_phase_id,
                'title'       => $proposal->title,
                'description' => $proposal->description,
                'status'      => 'planned',
                'position'    => Task::getNextPositionInColumn($column->id),
                'due_date'    => $proposal->due_date?->format('Y-m-d'),
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ]);

            if ($proposal->suggested_assignee_id) {
                $task->users()->sync([$proposal->suggested_assignee_id]);
            }

            $proposal->update([
                'status'          => ProjectIntakeProposal::STATUS_APPROVED,
                'created_task_id' => $task->id,
            ]);

            $created++;
        }

        $noun = $created === 1 ? 'task' : 'tasks';

        // Notify project Slack channel — failure must not prevent the redirect.
        if ($created > 0) {
            $approvedTitles = $proposals
                ->filter(fn ($p) => $p->status === ProjectIntakeProposal::STATUS_APPROVED)
                ->pluck('title')
                ->all();
            app(ProjectSlackNotificationService::class)
                ->notifyIntakeApproval($project, $intake, $approvedTitles);
        }

        return redirect()->route('projects.intake.show', [$project, $intake])
            ->with('success', "{$created} {$noun} added to {$project->name}.");
    }

    public function dismissProposal(
        Request $request,
        Project $project,
        ProjectIntake $intake,
        ProjectIntakeProposal $proposal
    ): RedirectResponse {
        $this->authorizeAccess($project);
        $this->resolveIntake($project, $intake);

        if ($proposal->project_intake_id !== $intake->id) {
            abort(404);
        }

        if (!$proposal->isPending()) {
            return redirect()->route('projects.intake.show', [$project, $intake]);
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', Rule::in(array_keys(ProjectIntakeProposal::DISMISSAL_REASONS))],
        ]);

        $proposal->update([
            'status'             => ProjectIntakeProposal::STATUS_DISMISSED,
            'disposition_reason' => $validated['reason'] ?? null,
        ]);

        return redirect()->route('projects.intake.show', [$project, $intake]);
    }

    private function loadFormData(Project $project): array
    {
        $phases = $project->phases()
            ->whereIn('status', ['planned', 'active'])
            ->orderByRaw('CASE WHEN status = "active" THEN 1 ELSE 2 END')
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        $users = User::where('active', true)
            ->where('role', '!=', 'client')
            ->orderBy('name')
            ->get(['id', 'name']);

        return [$phases, $users];
    }
}
