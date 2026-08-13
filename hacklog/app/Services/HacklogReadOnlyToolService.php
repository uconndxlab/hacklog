<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class HacklogReadOnlyToolService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getToolDefinitions(): array
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'count_open_tasks_by_project',
                    'description' => 'Returns visible projects ranked by open task count. Use for backlog or workload questions. Not suitable for urgency or deadline questions.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 50,
                                'description' => 'Maximum number of projects to return.',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_open_tasks_for_project',
                    'description' => 'List open tasks for one visible project by ID or name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'project_id' => [
                                'type' => 'integer',
                                'minimum' => 1,
                            ],
                            'project_name' => [
                                'type' => 'string',
                                'minLength' => 1,
                                'maxLength' => 120,
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 50,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'find_user_by_name',
                    'description' => 'Find active users by partial name match.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => [
                                'type' => 'string',
                                'minLength' => 1,
                                'maxLength' => 120,
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 25,
                            ],
                        ],
                        'required' => ['name'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_open_tasks_for_user',
                    'description' => 'List open tasks assigned to a specific user in projects visible to the current user.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'user_id' => [
                                'type' => 'integer',
                                'minimum' => 1,
                            ],
                            'limit' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 50,
                            ],
                        ],
                        'required' => ['user_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_overdue_open_tasks',
                    'description' => 'Lists individual overdue open tasks. Use for detailed drill-down into overdue items. For a per-project overdue or urgency summary, use get_project_urgency_summary.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 50,
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_project_urgency_summary',
                    'description' => 'Per-project urgency summary. Use for urgency, priority, deadline, overdue, "this week", or project health questions. Returns per-project counts: overdue tasks, tasks due in 7 days, high-priority open tasks, awaiting-feedback tasks. Sorted by urgency.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => [
                                'type' => 'integer',
                                'minimum' => 1,
                                'maximum' => 50,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, validated_arguments?: array<string, mixed>, result?: array<string, mixed>, error?: string}
     */
    public function execute(User $actor, string $toolName, array $arguments): array
    {
        return match ($toolName) {
            'count_open_tasks_by_project' => $this->countOpenTasksByProject($actor, $arguments),
            'list_open_tasks_for_project' => $this->listOpenTasksForProject($actor, $arguments),
            'find_user_by_name' => $this->findUserByName($arguments),
            'list_open_tasks_for_user' => $this->listOpenTasksForUser($actor, $arguments),
            'list_overdue_open_tasks' => $this->listOverdueOpenTasks($actor, $arguments),
            'get_project_urgency_summary' => $this->getProjectUrgencySummary($actor, $arguments),
            default => [
                'ok' => false,
                'error' => 'Unknown tool requested.',
            ],
        };
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, validated_arguments?: array<string, mixed>, result?: array<string, mixed>, error?: string}
     */
    protected function countOpenTasksByProject(User $actor, array $arguments): array
    {
        $validator = Validator::make($arguments, [
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'error' => $validator->errors()->first()];
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 10);

        $visibleProjects = Project::visibleTo($actor)->select('id', 'name')->get()->keyBy('id');
        $visibleProjectIds = $visibleProjects->keys()->all();

        if (empty($visibleProjectIds)) {
            return [
                'ok' => true,
                'validated_arguments' => ['limit' => $limit],
                'result' => [
                    'rows' => [],
                    'top_project' => null,
                ],
            ];
        }

        $counts = Task::withoutGlobalScope('ordered')
            ->selectRaw('columns.project_id as project_id, COUNT(tasks.id) as open_tasks_count')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->whereIn('columns.project_id', $visibleProjectIds)
            ->where('tasks.status', '!=', 'completed')
            ->groupBy('columns.project_id')
            ->get()
            ->map(function ($row) use ($visibleProjects): ?array {
                $project = $visibleProjects->get((int) $row->project_id);
                if (!$project) {
                    return null;
                }

                return [
                    'project_id' => (int) $project->id,
                    'project_name' => (string) $project->name,
                    'open_tasks_count' => (int) $row->open_tasks_count,
                ];
            })
            ->filter()
            ->sortByDesc('open_tasks_count')
            ->values()
            ->take($limit)
            ->values();

        return [
            'ok' => true,
            'validated_arguments' => ['limit' => $limit],
            'result' => [
                'rows' => $counts->all(),
                'top_project' => $counts->first(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, validated_arguments?: array<string, mixed>, result?: array<string, mixed>, error?: string}
     */
    protected function listOpenTasksForProject(User $actor, array $arguments): array
    {
        $validator = Validator::make($arguments, [
            'project_id' => 'nullable|integer|min:1',
            'project_name' => 'nullable|string|min:1|max:120',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $validator->after(function ($validator) use ($arguments): void {
            $projectId = $arguments['project_id'] ?? null;
            $projectName = trim((string) ($arguments['project_name'] ?? ''));
            if ($projectId === null && $projectName === '') {
                $validator->errors()->add('project_id', 'Either project_id or project_name is required.');
            }
        });

        if ($validator->fails()) {
            return ['ok' => false, 'error' => $validator->errors()->first()];
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 20);

        $projectQuery = Project::visibleTo($actor);

        if (!empty($validated['project_id'])) {
            $projectQuery->where('id', (int) $validated['project_id']);
        } else {
            $projectName = trim((string) ($validated['project_name'] ?? ''));
            $projectQuery->where('name', 'like', '%'.$projectName.'%');
        }

        $project = $projectQuery->orderBy('name')->first();

        if (!$project) {
            return [
                'ok' => true,
                'validated_arguments' => [
                    'project_id' => $validated['project_id'] ?? null,
                    'project_name' => $validated['project_name'] ?? null,
                    'limit' => $limit,
                ],
                'result' => [
                    'project' => null,
                    'tasks' => [],
                ],
            ];
        }

        $tasks = Task::withoutGlobalScope('ordered')
            ->whereHas('column', function ($query) use ($project): void {
                $query->where('project_id', $project->id);
            })
            ->where('status', '!=', 'completed')
            ->with(['users:id,name', 'phase:id,name,end_date'])
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function (Task $task): array {
                return [
                    'task_id' => (int) $task->id,
                    'title' => (string) $task->title,
                    'status' => (string) $task->status,
                    'due_date' => $task->due_date?->format('Y-m-d'),
                    'effective_due_date' => $task->getEffectiveDueDate()?->format('Y-m-d'),
                    'assignees' => $task->users->pluck('name')->values()->all(),
                ];
            })
            ->values();

        return [
            'ok' => true,
            'validated_arguments' => [
                'project_id' => $validated['project_id'] ?? null,
                'project_name' => $validated['project_name'] ?? null,
                'limit' => $limit,
            ],
            'result' => [
                'project' => [
                    'id' => (int) $project->id,
                    'name' => (string) $project->name,
                    'status' => (string) $project->status,
                ],
                'tasks' => $tasks->all(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, validated_arguments?: array<string, mixed>, result?: array<string, mixed>, error?: string}
     */
    protected function findUserByName(array $arguments): array
    {
        $validator = Validator::make($arguments, [
            'name' => 'required|string|min:1|max:120',
            'limit' => 'nullable|integer|min:1|max:25',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'error' => $validator->errors()->first()];
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 10);
        $name = trim((string) $validated['name']);

        $users = User::query()
            ->where('active', true)
            ->where('name', 'like', '%'.$name.'%')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'role'])
            ->map(function (User $user): array {
                return [
                    'id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'email' => (string) $user->email,
                    'role' => (string) $user->role,
                ];
            })
            ->values();

        return [
            'ok' => true,
            'validated_arguments' => [
                'name' => $name,
                'limit' => $limit,
            ],
            'result' => [
                'users' => $users->all(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, validated_arguments?: array<string, mixed>, result?: array<string, mixed>, error?: string}
     */
    protected function listOpenTasksForUser(User $actor, array $arguments): array
    {
        $validator = Validator::make($arguments, [
            'user_id' => 'required|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'error' => $validator->errors()->first()];
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 20);
        $userId = (int) $validated['user_id'];

        $assignee = User::query()->where('active', true)->find($userId);
        if (!$assignee) {
            return [
                'ok' => true,
                'validated_arguments' => [
                    'user_id' => $userId,
                    'limit' => $limit,
                ],
                'result' => [
                    'assignee' => null,
                    'tasks' => [],
                ],
            ];
        }

        $visibleProjectIds = Project::visibleTo($actor)->pluck('id');

        $tasks = Task::withoutGlobalScope('ordered')
            ->whereHas('users', function ($query) use ($userId): void {
                $query->where('users.id', $userId);
            })
            ->whereHas('column', function ($query) use ($visibleProjectIds): void {
                $query->whereIn('project_id', $visibleProjectIds);
            })
            ->where('status', '!=', 'completed')
            ->with(['column.project:id,name', 'phase:id,name,end_date'])
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date', 'asc')
            ->limit($limit)
            ->get()
            ->map(function (Task $task): array {
                return [
                    'task_id' => (int) $task->id,
                    'title' => (string) $task->title,
                    'status' => (string) $task->status,
                    'project_name' => (string) $task->column->project->name,
                    'effective_due_date' => $task->getEffectiveDueDate()?->format('Y-m-d'),
                ];
            })
            ->values();

        return [
            'ok' => true,
            'validated_arguments' => [
                'user_id' => $userId,
                'limit' => $limit,
            ],
            'result' => [
                'assignee' => [
                    'id' => (int) $assignee->id,
                    'name' => (string) $assignee->name,
                ],
                'tasks' => $tasks->all(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, validated_arguments?: array<string, mixed>, result?: array<string, mixed>, error?: string}
     */
    protected function listOverdueOpenTasks(User $actor, array $arguments): array
    {
        $validator = Validator::make($arguments, [
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'error' => $validator->errors()->first()];
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 20);

        $visibleProjectIds = Project::visibleTo($actor)->pluck('id');

        $tasks = Task::withoutGlobalScope('ordered')
            ->with(['column.project:id,name', 'phase:id,name,end_date', 'users:id,name'])
            ->whereHas('column', function ($query) use ($visibleProjectIds): void {
                $query->whereIn('project_id', $visibleProjectIds);
            })
            ->where('status', '!=', 'completed')
            ->where(function ($query): void {
                $query->whereNotNull('due_date')
                    ->orWhereHas('phase', function ($phaseQuery): void {
                        $phaseQuery->whereNotNull('end_date');
                    });
            })
            ->get()
            ->filter(function (Task $task): bool {
                $effectiveDueDate = $task->getEffectiveDueDate();
                return $effectiveDueDate && $effectiveDueDate->isBefore(today());
            })
            ->sortBy(function (Task $task) {
                return $task->getEffectiveDueDate();
            })
            ->take($limit)
            ->values()
            ->map(function (Task $task): array {
                return [
                    'task_id' => (int) $task->id,
                    'title' => (string) $task->title,
                    'status' => (string) $task->status,
                    'project_name' => (string) $task->column->project->name,
                    'effective_due_date' => $task->getEffectiveDueDate()?->format('Y-m-d'),
                    'assignees' => $task->users->pluck('name')->values()->all(),
                ];
            });

        return [
            'ok' => true,
            'validated_arguments' => [
                'limit' => $limit,
            ],
            'result' => [
                'tasks' => $tasks->all(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{ok: bool, validated_arguments?: array<string, mixed>, result?: array<string, mixed>, error?: string}
     */
    protected function getProjectUrgencySummary(User $actor, array $arguments): array
    {
        $validator = Validator::make($arguments, [
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'error' => $validator->errors()->first()];
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 20);

        $visibleProjects = Project::visibleTo($actor)->select('id', 'name', 'status')->get()->keyBy('id');
        $visibleProjectIds = $visibleProjects->keys()->all();

        if (empty($visibleProjectIds)) {
            return [
                'ok' => true,
                'validated_arguments' => ['limit' => $limit],
                'result' => [
                    'projects' => [],
                    'as_of_date' => today()->toDateString(),
                ],
            ];
        }

        $today = today()->toDateString();
        $in7Days = today()->addDays(7)->toDateString();

        // Overdue open tasks per project: effective due date < today, status != completed
        $overdue = Task::withoutGlobalScope('ordered')
            ->selectRaw('columns.project_id as project_id, COUNT(tasks.id) as cnt')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->leftJoin('phases', 'tasks.phase_id', '=', 'phases.id')
            ->whereIn('columns.project_id', $visibleProjectIds)
            ->where('tasks.status', '!=', 'completed')
            ->where(function ($q) use ($today) {
                $q->where(function ($inner) use ($today) {
                    $inner->whereNotNull('tasks.due_date')
                          ->where('tasks.due_date', '<', $today);
                })->orWhere(function ($inner) use ($today) {
                    $inner->whereNull('tasks.due_date')
                          ->whereNotNull('phases.end_date')
                          ->where('phases.end_date', '<', $today);
                });
            })
            ->groupBy('columns.project_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->project_id)
            ->map(fn ($row) => (int) $row->cnt);

        // Tasks due within 7 days: today <= effective due date <= today+7, status != completed
        $dueWithin7 = Task::withoutGlobalScope('ordered')
            ->selectRaw('columns.project_id as project_id, COUNT(tasks.id) as cnt')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->leftJoin('phases', 'tasks.phase_id', '=', 'phases.id')
            ->whereIn('columns.project_id', $visibleProjectIds)
            ->where('tasks.status', '!=', 'completed')
            ->where(function ($q) use ($today, $in7Days) {
                $q->where(function ($inner) use ($today, $in7Days) {
                    $inner->whereNotNull('tasks.due_date')
                          ->where('tasks.due_date', '>=', $today)
                          ->where('tasks.due_date', '<=', $in7Days);
                })->orWhere(function ($inner) use ($today, $in7Days) {
                    $inner->whereNull('tasks.due_date')
                          ->whereNotNull('phases.end_date')
                          ->where('phases.end_date', '>=', $today)
                          ->where('phases.end_date', '<=', $in7Days);
                });
            })
            ->groupBy('columns.project_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->project_id)
            ->map(fn ($row) => (int) $row->cnt);

        // High-priority open tasks per project
        $highPriority = Task::withoutGlobalScope('ordered')
            ->selectRaw('columns.project_id as project_id, COUNT(tasks.id) as cnt')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->whereIn('columns.project_id', $visibleProjectIds)
            ->where('tasks.status', '!=', 'completed')
            ->where('tasks.priority', 'high')
            ->groupBy('columns.project_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->project_id)
            ->map(fn ($row) => (int) $row->cnt);

        // Awaiting-feedback tasks per project (stalled/blocked signal)
        $awaitingFeedback = Task::withoutGlobalScope('ordered')
            ->selectRaw('columns.project_id as project_id, COUNT(tasks.id) as cnt')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->whereIn('columns.project_id', $visibleProjectIds)
            ->where('tasks.status', 'awaiting_feedback')
            ->groupBy('columns.project_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->project_id)
            ->map(fn ($row) => (int) $row->cnt);

        $rows = $visibleProjects
            ->map(function (Project $project) use ($overdue, $dueWithin7, $highPriority, $awaitingFeedback): array {
                return [
                    'project_id' => (int) $project->id,
                    'project_name' => (string) $project->name,
                    'project_status' => (string) $project->status,
                    'overdue_open_tasks' => $overdue->get($project->id, 0),
                    'due_within_7_days' => $dueWithin7->get($project->id, 0),
                    'high_priority_open_tasks' => $highPriority->get($project->id, 0),
                    'awaiting_feedback_tasks' => $awaitingFeedback->get($project->id, 0),
                ];
            })
            ->sortByDesc(function (array $row): int {
                return $row['overdue_open_tasks'] * 100
                    + $row['due_within_7_days'] * 10
                    + $row['high_priority_open_tasks'];
            })
            ->values()
            ->take($limit)
            ->values();

        return [
            'ok' => true,
            'validated_arguments' => ['limit' => $limit],
            'result' => [
                'projects' => $rows->all(),
                'as_of_date' => today()->toDateString(),
            ],
        ];
    }
}
