<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class TimelineController extends Controller
{
    private const PHASE_STATUS_OPTIONS = ['planning', 'active', 'on_hold', 'completed'];

    /**
     * Display the organization-wide timeline.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $this->normalizeFilters($request);

        $visibleProjectsQuery = Project::query()->visibleTo($user);
        $this->applyProjectFilters($visibleProjectsQuery, $filters);
        $visibleProjectIds = $visibleProjectsQuery->pluck('id')->all();

        $phasesQuery = Phase::query()
            ->whereIn('project_id', $visibleProjectIds)
            ->where(function ($query) {
                $query->whereNotNull('start_date')
                    ->orWhereNotNull('end_date');
            })
            ->with([
                'project',
                'tasks' => function ($query) use ($filters) {
                    $this->applyTaskFilters($query, $filters);
                },
            ]);

        if (!empty($filters['phase_statuses'])) {
            $phasesQuery->whereIn('status', $filters['phase_statuses']);
        }

        if ($filters['has_task_statuses']) {
            $phasesQuery->whereHas('tasks', function ($query) use ($filters) {
                $query->whereIn('status', $filters['task_statuses']);
            });
        }

        if (!empty($filters['assignee_ids'])) {
            $phasesQuery->whereHas('tasks.users', function ($query) use ($filters) {
                $query->whereIn('users.id', $filters['assignee_ids']);
            });
        }

        $this->applyDateFiltersToPhases($phasesQuery, $filters['start_date'], $filters['end_date']);

        if ($filters['overdue_only']) {
            $today = Carbon::today();
            $phasesQuery->where(function ($query) use ($today, $filters) {
                $query->where(function ($phaseOverdue) use ($today) {
                    $phaseOverdue->whereNotNull('end_date')
                        ->whereDate('end_date', '<', $today)
                        ->where('status', '!=', 'completed');
                })->orWhereHas('tasks', function ($taskQuery) use ($today, $filters) {
                    $this->applyTaskFilters($taskQuery, $filters);
                    $taskQuery->where('status', '!=', 'completed')
                        ->where(function ($dateQuery) use ($today) {
                            $dateQuery->whereDate('due_date', '<', $today)
                                ->orWhere(function ($inheritedQuery) use ($today) {
                                    $inheritedQuery->whereNull('due_date')
                                        ->whereHas('phase', function ($phaseQuery) use ($today) {
                                            $phaseQuery->whereNotNull('end_date')
                                                ->whereDate('end_date', '<', $today)
                                                ->where('status', '!=', 'completed');
                                        });
                                });
                        });
                });
            });
        }

        $phases = $phasesQuery
            ->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date', 'asc')
            ->orderBy('end_date', 'asc')
            ->get();

        [$timelineStart, $timelineEnd] = $this->resolveTimelineBounds($filters);

        if (!$filters['has_start']) {
            $filters['start'] = Carbon::today()->format('Y-m-d');
        }

        if (!$filters['has_end']) {
            $filters['end'] = Carbon::today()->addMonths(2)->format('Y-m-d');
        }

        $weeks = [];
        $weekCount = $timelineStart->diffInWeeks($timelineEnd) + 1;
        $tooWide = $weekCount > 26;
        $displayWeeks = min($weekCount, 26);
        $currentWeek = $timelineStart->copy();

        for ($i = 0; $i < $displayWeeks; $i++) {
            $weekStart = $currentWeek->copy();
            $weekEnd = $currentWeek->copy()->endOfWeek();
            $label = $weekStart->month === $weekEnd->month
                ? $weekStart->format('M j') . '-' . $weekEnd->format('j')
                : $weekStart->format('M j') . ' - ' . $weekEnd->format('M j');

            $weeks[] = [
                'start' => $weekStart,
                'end' => $weekEnd,
                'label' => $label,
                'due_count' => 0,
            ];
            $currentWeek->addWeek();
        }

        $tasks = Task::with(['phase', 'column.project', 'users'])
            ->whereHas('column.project', function ($query) use ($visibleProjectIds) {
                $query->whereIn('id', $visibleProjectIds);
            });

        $this->applyTaskFilters($tasks, $filters);

        if ($filters['has_phase_statuses']) {
            $tasks->where(function ($query) use ($filters) {
                $query->whereNull('phase_id')
                    ->orWhereHas('phase', function ($phaseQuery) use ($filters) {
                        $phaseQuery->whereIn('status', $filters['phase_statuses']);
                    });
            });
        }

        if ($filters['overdue_only']) {
            $today = Carbon::today();
            $tasks->where('status', '!=', 'completed')
                ->where(function ($query) use ($today) {
                    $query->whereDate('due_date', '<', $today)
                        ->orWhere(function ($inheritedQuery) use ($today) {
                            $inheritedQuery->whereNull('due_date')
                                ->whereHas('phase', function ($phaseQuery) use ($today) {
                                    $phaseQuery->whereNotNull('end_date')
                                        ->whereDate('end_date', '<', $today)
                                        ->where('status', '!=', 'completed');
                                });
                        });
                });
        }

        $tasks = $tasks->get();

        foreach ($weeks as $index => $week) {
            $dueDateCount = 0;

            foreach ($phases as $phase) {
                if ($phase->end_date
                    && $phase->end_date->gte($week['start'])
                    && $phase->end_date->lte($week['end'])) {
                    $dueDateCount++;
                }
            }

            foreach ($tasks as $task) {
                $effectiveDueDate = $task->getEffectiveDueDate();
                if ($effectiveDueDate
                    && $effectiveDueDate->gte($week['start'])
                    && $effectiveDueDate->lte($week['end'])) {
                    $dueDateCount++;
                }
            }

            $weeks[$index]['due_count'] = $dueDateCount;
        }

        $projects = $phases->groupBy('project_id')->map(function ($projectPhases) use ($filters) {
            $project = $projectPhases->first()->project;

            $phasesWithTaskCounts = $projectPhases->map(function ($phase) {
                $taskCounts = $phase->tasks->countBy('status')->toArray();

                $phase->task_counts = array_merge([
                    'planned' => 0,
                    'active' => 0,
                    'awaiting_feedback' => 0,
                    'completed' => 0,
                ], $taskCounts);

                return $phase;
            });

            $assigneesQuery = User::query()
                ->where('active', true)
                ->whereHas('tasks', function ($query) use ($project, $filters) {
                    $query->whereHas('column.project', function ($projectQuery) use ($project) {
                        $projectQuery->where('id', $project->id);
                    });
                    $this->applyTaskFilters($query, $filters);
                });

            if (!empty($filters['assignee_ids'])) {
                $assigneesQuery->whereIn('id', $filters['assignee_ids']);
            }

            $assignees = $assigneesQuery->select('id', 'name')->distinct()->orderBy('name')->get();

            $assigneeData = $assignees->map(function ($member) {
                $nameParts = preg_split('/\s+/', trim($member->name)) ?: [];
                $first = $nameParts[0] ?? '';
                $second = $nameParts[1] ?? '';

                return [
                    'user' => $member,
                    'initials' => strtoupper(substr($first, 0, 1) . substr($second, 0, 1)),
                ];
            })->sortBy('initials')->values();

            return [
                'project' => $project,
                'phases' => $phasesWithTaskCounts,
                'assignees' => [
                    'count' => $assigneeData->count(),
                    'data' => $assigneeData,
                ],
            ];
        })->values();

        $filterOptions = $this->buildFilterOptions($user);

        $viewData = [
            'projects' => $projects,
            'phases' => $phases,
            'weeks' => $weeks,
            'timelineStart' => $timelineStart,
            'timelineEnd' => $timelineEnd,
            'tooWide' => $tooWide,
            'filters' => $filters,
            'filterOptions' => $filterOptions,
            'filterStart' => $filters['start'],
            'filterEnd' => $filters['end'],
            'showCompleted' => $filters['show_completed'],
        ];

        if ($request->header('HX-Request')) {
            return view('timeline.partials.page', $viewData);
        }

        return view('timeline.index', $viewData);
    }

    private function normalizeFilters(Request $request): array
    {
        $showCompleted = $request->boolean('show_completed');

        $startInput = $request->input('start');
        $endInput = $request->input('end');

        $startDate = $startInput ? Carbon::parse($startInput)->startOfDay() : null;
        $endDate = $endInput ? Carbon::parse($endInput)->endOfDay() : null;

        $defaultProjectStatuses = $showCompleted
            ? Project::STATUS_VALUES
            : [Project::STATUS_PLANNING, Project::STATUS_ACTIVE, Project::STATUS_ON_HOLD];
        $defaultPhaseStatuses = $showCompleted
            ? self::PHASE_STATUS_OPTIONS
            : ['planning', 'active', 'on_hold'];
        $defaultTaskStatuses = $showCompleted
            ? Task::STATUSES
            : ['planned', 'active', 'awaiting_feedback'];

        return [
            'start' => $startInput,
            'end' => $endInput,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'show_completed' => $showCompleted,
            'overdue_only' => $request->boolean('overdue_only'),

            'project_ids' => $this->normalizeIntArray($request->input('project_ids', [])),
            'assignee_ids' => $this->normalizeIntArray($request->input('assignee_ids', [])),
            'tag_ids' => $this->normalizeIntArray($request->input('tag_ids', [])),

            'project_statuses' => $this->normalizeStringArray(
                $request->input('project_statuses', $defaultProjectStatuses),
                Project::STATUS_VALUES
            ),
            'phase_statuses' => $this->normalizeStringArray(
                $request->input('phase_statuses', $defaultPhaseStatuses),
                self::PHASE_STATUS_OPTIONS
            ),
            'task_statuses' => $this->normalizeStringArray(
                $request->input('task_statuses', $defaultTaskStatuses),
                Task::STATUSES
            ),
            'staffing_models' => $this->normalizeStringArray(
                $request->input('staffing_models', Project::STAFFING_MODELS),
                Project::STAFFING_MODELS
            ),

            'has_start' => $request->filled('start'),
            'has_end' => $request->filled('end'),
            'has_project_ids' => $request->has('project_ids'),
            'has_assignee_ids' => $request->has('assignee_ids'),
            'has_tag_ids' => $request->has('tag_ids'),
            'has_project_statuses' => $request->has('project_statuses'),
            'has_phase_statuses' => $request->has('phase_statuses'),
            'has_task_statuses' => $request->has('task_statuses'),
            'has_staffing_models' => $request->has('staffing_models'),
        ];
    }

    private function normalizeIntArray(array $values): array
    {
        return collect($values)
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (int) $value)
            ->unique()
            ->values()
            ->all();
    }

    private function normalizeStringArray(array $values, array $allowed): array
    {
        $allowedSet = collect($allowed)->map(fn ($value) => (string) $value)->all();

        $normalized = collect($values)
            ->map(fn ($value) => (string) $value)
            ->filter(fn ($value) => in_array($value, $allowedSet, true))
            ->unique()
            ->values()
            ->all();

        return !empty($normalized) ? $normalized : $allowedSet;
    }

    private function applyProjectFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['project_ids'])) {
            $query->whereIn('id', $filters['project_ids']);
        }

        if (!empty($filters['project_statuses'])) {
            $query->whereIn('status', $filters['project_statuses']);
        }

        if (!empty($filters['staffing_models'])) {
            $query->whereIn('staffing_model', $filters['staffing_models']);
        }

        if (!empty($filters['tag_ids'])) {
            $query->whereHas('tags', function ($tagQuery) use ($filters) {
                $tagQuery->whereIn('tags.id', $filters['tag_ids']);
            });
        }
    }

    private function applyTaskFilters($query, array $filters): void
    {
        if (!empty($filters['task_statuses'])) {
            $query->whereIn('status', $filters['task_statuses']);
        }

        if (!empty($filters['assignee_ids'])) {
            $query->whereHas('users', function ($assigneeQuery) use ($filters) {
                $assigneeQuery->whereIn('users.id', $filters['assignee_ids']);
            });
        }
    }

    private function applyDateFiltersToPhases(Builder $query, ?Carbon $filterStart, ?Carbon $filterEnd): void
    {
        if (!$filterStart && !$filterEnd) {
            return;
        }

        $query->where(function ($dateQuery) use ($filterStart, $filterEnd) {
            if ($filterStart && $filterEnd) {
                $dateQuery->where(function ($overlapQuery) use ($filterStart, $filterEnd) {
                    $overlapQuery->whereBetween('start_date', [$filterStart, $filterEnd])
                        ->orWhereBetween('end_date', [$filterStart, $filterEnd])
                        ->orWhere(function ($spanningQuery) use ($filterStart, $filterEnd) {
                            $spanningQuery->where('start_date', '<=', $filterStart)
                                ->where('end_date', '>=', $filterEnd);
                        });
                });
                return;
            }

            if ($filterStart) {
                $dateQuery->where(function ($startQuery) use ($filterStart) {
                    $startQuery->where('start_date', '>=', $filterStart)
                        ->orWhere('end_date', '>=', $filterStart);
                });
            }

            if ($filterEnd) {
                $dateQuery->where(function ($endQuery) use ($filterEnd) {
                    $endQuery->where('start_date', '<=', $filterEnd)
                        ->orWhere('end_date', '<=', $filterEnd);
                });
            }
        });
    }

    private function resolveTimelineBounds(array $filters): array
    {
        if ($filters['start_date'] && $filters['end_date']) {
            return [
                $filters['start_date']->copy()->startOfWeek(),
                $filters['end_date']->copy()->endOfWeek(),
            ];
        }

        if ($filters['start_date'] && !$filters['end_date']) {
            return [
                $filters['start_date']->copy()->startOfWeek(),
                $filters['start_date']->copy()->addMonths(2)->endOfWeek(),
            ];
        }

        if (!$filters['start_date'] && $filters['end_date']) {
            return [
                $filters['end_date']->copy()->subMonths(2)->startOfWeek(),
                $filters['end_date']->copy()->endOfWeek(),
            ];
        }

        $today = Carbon::today();
        return [
            $today->copy()->startOfWeek(),
            $today->copy()->addMonths(2)->endOfWeek(),
        ];
    }

    private function buildFilterOptions(User $user): array
    {
        $visibleProjects = Project::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'staffing_model']);

        $visibleProjectIds = $visibleProjects->pluck('id')->all();

        $tags = Tag::query()
            ->whereHas('projects', function ($query) use ($visibleProjectIds) {
                $query->whereIn('projects.id', $visibleProjectIds);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $assignees = User::query()
            ->where('active', true)
            ->whereHas('tasks.column.project', function ($query) use ($visibleProjectIds) {
                $query->whereIn('projects.id', $visibleProjectIds);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return [
            'projects' => $visibleProjects,
            'tags' => $tags,
            'assignees' => $assignees,
            'project_statuses' => Project::STATUS_VALUES,
            'phase_statuses' => self::PHASE_STATUS_OPTIONS,
            'task_statuses' => Task::STATUSES,
            'staffing_models' => Project::STAFFING_MODELS,
        ];
    }
}
