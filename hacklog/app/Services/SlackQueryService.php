<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * Deterministic project task queries for the Hacklog Slack bot.
 *
 * Intentionally decoupled from Slack: these methods return plain arrays
 * that can also serve a future REST API, web UI widget, or AI-routed query.
 *
 * Uses Hacklog's existing task semantics:
 *   - "open"    = status != 'completed'
 *   - "overdue" = effective due date < today AND status != 'completed'
 *   - "due this week" = effective due date in [today, end of current week]
 *
 * "Effective due date" follows Task::getEffectiveDueDate():
 *   task.due_date if set, otherwise phase.end_date.
 */
class SlackQueryService
{
    /**
     * Return open tasks whose effective due date falls within the current calendar week.
     *
     * @return array<int, array{title: string, effective_due_date: string|null, assignees: string[]}>
     */
    public function dueThisWeek(Project $project, int $limit = 15): array
    {
        $today      = today()->toDateString();
        $endOfWeek  = today()->endOfWeek()->toDateString();   // end of Sunday

        $tasks = Task::withoutGlobalScope('ordered')
            ->select('tasks.*')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->leftJoin('phases', 'tasks.phase_id', '=', 'phases.id')
            ->where('columns.project_id', $project->id)
            ->where('tasks.status', '!=', 'completed')
            ->where(function ($q) use ($today, $endOfWeek) {
                $q->where(function ($inner) use ($today, $endOfWeek) {
                    $inner->whereNotNull('tasks.due_date')
                          ->where('tasks.due_date', '>=', $today)
                          ->where('tasks.due_date', '<=', $endOfWeek);
                })->orWhere(function ($inner) use ($today, $endOfWeek) {
                    $inner->whereNull('tasks.due_date')
                          ->whereNotNull('phases.end_date')
                          ->where('phases.end_date', '>=', $today)
                          ->where('phases.end_date', '<=', $endOfWeek);
                });
            })
            ->orderByRaw('COALESCE(tasks.due_date, phases.end_date) ASC')
            ->limit($limit)
            ->with(['users:id,name', 'phase:id,end_date'])
            ->get();

        return $tasks->map(function (Task $task): array {
            $effectiveDate = $task->getEffectiveDueDate();
            return [
                'title'              => $task->title,
                'effective_due_date' => $effectiveDate?->toDateString(),
                'assignees'          => $task->users->pluck('name')->values()->all(),
            ];
        })->all();
    }

    /**
     * Return open tasks whose effective due date has already passed.
     *
     * @return array<int, array{title: string, effective_due_date: string|null, days_overdue: int, assignees: string[]}>
     */
    public function overdue(Project $project, int $limit = 15): array
    {
        $today = today()->toDateString();

        $tasks = Task::withoutGlobalScope('ordered')
            ->select('tasks.*')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->leftJoin('phases', 'tasks.phase_id', '=', 'phases.id')
            ->where('columns.project_id', $project->id)
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
            ->orderByRaw('COALESCE(tasks.due_date, phases.end_date) ASC')
            ->limit($limit)
            ->with(['users:id,name', 'phase:id,end_date'])
            ->get();

        return $tasks->map(function (Task $task): array {
            $effectiveDate = $task->getEffectiveDueDate();
            $daysOverdue   = $effectiveDate ? (int) today()->diffInDays($effectiveDate) : 0;
            return [
                'title'              => $task->title,
                'effective_due_date' => $effectiveDate?->toDateString(),
                'days_overdue'       => $daysOverdue,
                'assignees'          => $task->users->pluck('name')->values()->all(),
            ];
        })->all();
    }

    /**
     * Return all open (non-completed) tasks for the project.
     *
     * @return array{
     *   total: int,
     *   tasks: array<int, array{title: string, status: string, assignees: string[]}>,
     * }
     */
    public function open(Project $project, int $limit = 10): array
    {
        $total = Task::withoutGlobalScope('ordered')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->where('columns.project_id', $project->id)
            ->where('tasks.status', '!=', 'completed')
            ->count();

        $tasks = Task::withoutGlobalScope('ordered')
            ->select('tasks.*')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->where('columns.project_id', $project->id)
            ->where('tasks.status', '!=', 'completed')
            ->orderByRaw('tasks.position IS NULL, tasks.position ASC')
            ->limit($limit)
            ->with(['users:id,name'])
            ->get()
            ->map(function (Task $task): array {
                return [
                    'title'     => $task->title,
                    'status'    => $task->status,
                    'assignees' => $task->users->pluck('name')->values()->all(),
                ];
            })
            ->all();

        return compact('total', 'tasks');
    }

    /**
     * Return actionable open tasks assigned to one user, either in one project
     * or across all non-archived / non-completed projects. Completed and
     * awaiting-feedback tasks are omitted.
     *
     * @return array{
     *   total: int,
     *   tasks: array<int, array{title: string, status: string, project_name: string, project_id: int, assignees: string[]}>,
     * }
     */
    public function openForUser(User $user, ?Project $project = null, int $limit = 10): array
    {
        $baseQuery = function () use ($user, $project) {
            $query = Task::withoutGlobalScope('ordered')
                ->select('tasks.*')
                ->join('columns', 'tasks.column_id', '=', 'columns.id')
                ->join('projects', 'columns.project_id', '=', 'projects.id')
                ->whereNotIn('tasks.status', ['completed', 'awaiting_feedback'])
                ->whereHas('users', fn ($q) => $q->where('users.id', $user->id));

            if ($project) {
                $query->where('columns.project_id', $project->id);
            } else {
                $query->whereNotIn('projects.status', [
                    Project::STATUS_ARCHIVED,
                    Project::STATUS_COMPLETED,
                ]);
            }

            return $query;
        };

        $total = $baseQuery()->count('tasks.id');

        $tasks = $baseQuery()
            ->orderBy('projects.name')
            ->orderByRaw('tasks.position IS NULL, tasks.position ASC')
            ->limit($limit)
            ->with(['users:id,name', 'column:id,project_id', 'column.project:id,name'])
            ->get()
            ->map(function (Task $task): array {
                return [
                    'title' => $task->title,
                    'status' => $task->status,
                    'project_name' => $task->column->project->name,
                    'project_id' => $task->column->project_id,
                    'assignees' => $task->users->pluck('name')->values()->all(),
                ];
            })
            ->all();

        return compact('total', 'tasks');
    }
}
