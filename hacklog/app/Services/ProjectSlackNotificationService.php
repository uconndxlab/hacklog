<?php

namespace App\Services;

use App\Jobs\SendProjectSlackTaskSummaryJob;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProjectSlackNotificationService
{
    public const THROTTLE_SECONDS = 300;

    public function queueTaskCreated(Task $task): void
    {
        $this->queueTaskEvent($task, 'created');
    }

    public function queueTaskUpdated(Task $task): void
    {
        $this->queueTaskEvent($task, 'updated');
    }

    protected function queueTaskEvent(Task $task, string $eventType): void
    {
        $project = $task->getProject();

        if (!$project || blank($project->slack_webhook_url)) {
            return;
        }

        $projectId = (int) $project->id;
        $lock = Cache::lock($this->lockKey($projectId), 10);

        try {
            $lock->block(3, function () use ($projectId, $task, $eventType): void {
                $pending = $this->normalizePendingPayload(Cache::get($this->pendingKey($projectId), []));

                if ($eventType === 'created') {
                    $creatorName = $task->creator?->name;
                    if (!$creatorName && $task->created_by) {
                        $creatorName = $task->creator()->value('name');
                    }

                    $pending['created_tasks'][] = [
                        'event' => 'created',
                        'task_id' => (int) $task->id,
                        'title' => $task->title,
                        'creator' => $creatorName ?: 'Unknown',
                    ];
                }

                if ($eventType === 'updated') {
                    $pending['updated_task_ids'][] = (int) $task->id;
                    $pending['updated_task_ids'] = array_values(array_unique($pending['updated_task_ids']));
                }

                Cache::put(
                    $this->pendingKey($projectId),
                    $pending,
                    now()->addSeconds(self::THROTTLE_SECONDS + 600)
                );

                if (!Cache::has($this->scheduledKey($projectId))) {
                    SendProjectSlackTaskSummaryJob::dispatch($projectId)
                        ->delay(now()->addSeconds(self::THROTTLE_SECONDS));

                    Cache::put(
                        $this->scheduledKey($projectId),
                        true,
                        now()->addSeconds(self::THROTTLE_SECONDS + 60)
                    );
                }
            });
        } catch (\Throwable $exception) {
            Log::warning('Failed to enqueue project Slack task summary.', [
                'project_id' => $projectId,
                'task_id' => $task->id,
                'event_type' => $eventType,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function flushProjectSummary(int $projectId): void
    {
        $project = Project::find($projectId);

        if (!$project || blank($project->slack_webhook_url)) {
            $this->clearPendingState($projectId);
            return;
        }

        $lock = Cache::lock($this->lockKey($projectId), 10);

        try {
            $pendingPayload = $lock->block(3, function () use ($projectId): array {
                $payload = Cache::pull($this->pendingKey($projectId), []);
                Cache::forget($this->scheduledKey($projectId));

                return $this->normalizePendingPayload($payload);
            });
        } catch (\Throwable $exception) {
            Log::warning('Failed to flush project Slack task summary.', [
                'project_id' => $projectId,
                'error' => $exception->getMessage(),
            ]);
            return;
        }

        $pendingCreatedTasks = array_values(array_filter(
            $pendingPayload['created_tasks'],
            fn (array $task) => isset($task['task_id']) && (int) $task['task_id'] > 0
        ));
        $pendingUpdatedTaskIds = array_values(array_unique($pendingPayload['updated_task_ids']));

        if (empty($pendingCreatedTasks) && empty($pendingUpdatedTaskIds)) {
            return;
        }

        $createdCount = count($pendingCreatedTasks);
        $updatedCount = count($pendingUpdatedTaskIds);

        $messageLines = [];

        if ($createdCount > 0) {
            $messageLines[] = sprintf('*%s* has %d new task(s) in the last 5 minutes.', $project->name, $createdCount);
        } else {
            $messageLines[] = sprintf('*%s* had %d task(s) receive updates in the last 5 minutes.', $project->name, $updatedCount);
        }

        $visibleTasks = array_slice($pendingCreatedTasks, 0, 20);
        $taskIdsToHydrate = array_values(array_unique(array_map(
            fn (array $task) => (int) ($task['task_id'] ?? 0),
            $visibleTasks
        )));
        $hydratedTasksById = Task::query()
            ->whereIn('id', $taskIdsToHydrate)
            ->with('creator')
            ->get()
            ->keyBy('id');

        $createdTaskLines = array_map(function (array $task) use ($hydratedTasksById): string {
            $hydratedTask = $hydratedTasksById->get((int) ($task['task_id'] ?? 0));
            $title = trim((string) ($task['title'] ?? $hydratedTask?->title ?? 'Untitled task'));
            $creator = trim((string) ($task['creator'] ?? $hydratedTask?->creator?->name ?? 'Unknown'));

            return sprintf('• %s — %s', $title !== '' ? $title : 'Untitled task', $creator !== '' ? $creator : 'Unknown');
        }, $visibleTasks);

        if (!empty($createdTaskLines)) {
            $messageLines[] = '';
            $messageLines = array_merge($messageLines, $createdTaskLines);
        }

        if ($createdCount > count($visibleTasks)) {
            $messageLines[] = sprintf('• ...and %d more task(s)', $createdCount - count($visibleTasks));
        }

        if ($updatedCount > 0) {
            $messageLines[] = '';
            $messageLines[] = sprintf('and %d task(s) received updates.', $updatedCount);
        }

        $boardUrl = route('projects.board', ['project' => $project]);
        $messageLines[] = '';
        $messageLines[] = sprintf('<%s|Open project board>', $boardUrl);
        $message = implode("\n", $messageLines);

        $response = Http::timeout(10)->post($project->slack_webhook_url, [
            'text' => $message,
        ]);

        if ($response->failed()) {
            Log::warning('Slack webhook post failed for project task summary.', [
                'project_id' => $projectId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    /**
     * Post a concise summary to Slack when tasks are created from an AI Intake approval.
     *
     * This fires immediately (not throttled) and is separate from the board-level
     * task-created notifications. Slack failure is logged but never surfaces to the user
     * and never prevents tasks from being created.
     *
     * @param  \App\Models\Project        $project
     * @param  \App\Models\ProjectIntake  $intake
     * @param  string[]                   $taskTitles  Titles of tasks that were just created.
     */
    public function notifyIntakeApproval(
        \App\Models\Project $project,
        \App\Models\ProjectIntake $intake,
        array $taskTitles
    ): void {
        if (blank($project->slack_webhook_url) || empty($taskTitles)) {
            return;
        }

        $count = count($taskTitles);
        $noun  = $count === 1 ? 'task' : 'tasks';

        $lines   = ["*Hacklog update — {$project->name}*", ''];
        $lines[] = "{$count} {$noun} created from AI intake:";

        foreach (array_slice($taskTitles, 0, 15) as $title) {
            $lines[] = '• ' . $title;
        }

        if ($count > 15) {
            $lines[] = '• ...and ' . ($count - 15) . ' more';
        }

        $intakeUrl = route('projects.intake.show', [$project, $intake]);
        $lines[]   = '';
        $lines[]   = "<{$intakeUrl}|View intake>";

        try {
            $response = Http::timeout(10)->post($project->slack_webhook_url, [
                'text' => implode("\n", $lines),
            ]);

            if ($response->failed()) {
                Log::warning('Hacklog AI: intake Slack notification failed.', [
                    'project_id' => $project->id,
                    'intake_id'  => $intake->id,
                    'status'     => $response->status(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::warning('Hacklog AI: intake Slack notification exception.', [
                'project_id' => $project->id,
                'intake_id'  => $intake->id,
                'error'      => $exception->getMessage(),
            ]);
        }
    }

    protected function clearPendingState(int $projectId): void
    {
        Cache::forget($this->pendingKey($projectId));
        Cache::forget($this->scheduledKey($projectId));
    }

    protected function pendingKey(int $projectId): string
    {
        return "project:{$projectId}:slack-task-summary:pending";
    }

    protected function scheduledKey(int $projectId): string
    {
        return "project:{$projectId}:slack-task-summary:scheduled";
    }

    protected function lockKey(int $projectId): string
    {
        return "project:{$projectId}:slack-task-summary:lock";
    }

    /**
     * @return array{created_tasks: array<int, array{event?: string, task_id: int, title?: string, creator?: string}>, updated_task_ids: array<int, int>}
     */
    protected function normalizePendingPayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [
                'created_tasks' => [],
                'updated_task_ids' => [],
            ];
        }

        // Backward compatibility with earlier payload shape that stored only created task rows.
        if (array_is_list($payload)) {
            $legacyCreatedTasks = [];

            foreach ($payload as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $taskId = (int) ($item['task_id'] ?? 0);
                if ($taskId <= 0) {
                    continue;
                }

                $legacyCreatedTasks[] = [
                    'event' => 'created',
                    'task_id' => $taskId,
                    'title' => isset($item['title']) ? (string) $item['title'] : null,
                    'creator' => isset($item['creator']) ? (string) $item['creator'] : null,
                ];
            }

            return [
                'created_tasks' => $legacyCreatedTasks,
                'updated_task_ids' => [],
            ];
        }

        $createdTasks = $payload['created_tasks'] ?? [];
        $updatedTaskIds = $payload['updated_task_ids'] ?? [];

        $normalizedCreatedTasks = [];
        if (is_array($createdTasks)) {
            foreach ($createdTasks as $task) {
                if (!is_array($task)) {
                    continue;
                }

                $event = isset($task['event']) ? (string) $task['event'] : 'created';
                if ($event !== 'created') {
                    continue;
                }

                $taskId = (int) ($task['task_id'] ?? 0);
                if ($taskId <= 0) {
                    continue;
                }

                $normalizedCreatedTasks[] = [
                    'event' => 'created',
                    'task_id' => $taskId,
                    'title' => isset($task['title']) ? (string) $task['title'] : null,
                    'creator' => isset($task['creator']) ? (string) $task['creator'] : null,
                ];
            }
        }

        return [
            'created_tasks' => $normalizedCreatedTasks,
            'updated_task_ids' => is_array($updatedTaskIds)
                ? array_values(array_unique(array_map('intval', $updatedTaskIds)))
                : [],
        ];
    }
}
