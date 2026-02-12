<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserProfileController extends Controller
{
    public function show(User $user)
    {
        // Only admins can access user profiles
        abort_unless(auth()->user()->isAdmin(), 403);

        // Define date ranges for calculations
        $today = Carbon::today();
        $next7Days = $today->copy()->addDays(7);
        $next14Days = $today->copy()->addDays(14);
        $next30Days = $today->copy()->addDays(30);
        $last30Days = $today->copy()->subDays(30);

        // ===== CURRENT WORKLOAD SNAPSHOT (next 30 days window) =====
        
        // Get all currently assigned tasks (active: open or in_progress)
        $activeTasks = $user->tasks()
            ->whereIn('status', ['planned', 'active'])
            ->with(['phase', 'column.project'])
            ->get();

        // Count active tasks
        $totalActiveTasks = $activeTasks->count();
        
        // Count in-progress tasks
        $inProgressTasks = $activeTasks->where('status', 'active')->count();

        // Count overdue tasks (using effective due date)
        $overdueTasks = $activeTasks->filter(function ($task) use ($today) {
            $effectiveDueDate = $task->getEffectiveDueDate();
            return $effectiveDueDate && $effectiveDueDate->isBefore($today);
        })->count();

        // Count tasks due in next 7 days
        $dueNext7Days = $activeTasks->filter(function ($task) use ($today, $next7Days) {
            $effectiveDueDate = $task->getEffectiveDueDate();
            return $effectiveDueDate 
                && $effectiveDueDate->greaterThanOrEqualTo($today) 
                && $effectiveDueDate->lessThan($next7Days);
        })->count();

        // Count distinct active projects
        $distinctActiveProjects = $activeTasks->pluck('column.project_id')->unique()->filter()->count();

        // Count tasks without due dates (neither task nor phase has due date)
        $tasksWithoutDueDates = $activeTasks->filter(function ($task) {
            return !$task->getEffectiveDueDate();
        })->count();

        // ===== ACTIVITY FOOTPRINT (all-time) =====
        
        // Total tasks ever assigned
        $totalTasksAssigned = $user->tasks()->count();

        // Breakdown by status
        $allUserTasks = $user->tasks()->get();
        $tasksPlanned = $allUserTasks->where('status', 'planned')->count();
        $tasksActive = $allUserTasks->where('status', 'active')->count();
        $tasksCompleted = $allUserTasks->where('status', 'completed')->count();

        // Total distinct projects assigned across lifetime
        $distinctProjectsLifetime = DB::table('tasks')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->join('task_user', 'tasks.id', '=', 'task_user.task_id')
            ->where('task_user.user_id', $user->id)
            ->distinct()
            ->count('columns.project_id');

        // Total comments made
        $totalCommentsMade = TaskComment::where('user_id', $user->id)->count();

        // Total tasks created
        $totalTasksCreated = Task::where('created_by', $user->id)->count();

        // ===== ACTIVITY SNAPSHOT (last 30 days) =====
        
        // Tasks created in last 30 days
        $tasksCreatedLast30 = Task::where('created_by', $user->id)
            ->where('created_at', '>=', $last30Days)
            ->count();

        // Tasks updated in last 30 days (where user updated them)
        $tasksUpdatedLast30 = Task::where('updated_by', $user->id)
            ->where('updated_at', '>=', $last30Days)
            ->count();

        // Comments posted in last 30 days
        $commentsPostedLast30 = TaskComment::where('user_id', $user->id)
            ->where('created_at', '>=', $last30Days)
            ->count();

        // Tasks completed in last 30 days
        $tasksCompletedLast30 = $user->tasks()
            ->where('status', 'completed')
            ->where('tasks.updated_at', '>=', $last30Days)
            ->count();

        // ===== CHART DATA =====

        // A) Status Breakdown Donut (all currently assigned tasks, not just active)
        $allAssignedTasks = $user->tasks()->get();
        $statusBreakdown = [
            'open' => $allAssignedTasks->where('status', 'planned')->count(),
            'in_progress' => $allAssignedTasks->where('status', 'active')->count(),
            'done' => $allAssignedTasks->where('status', 'completed')->count(),
        ];

        // B) Due Pressure Bar (for active tasks only)
        $duePressure = [
            'overdue' => $overdueTasks,
            'next_7_days' => $dueNext7Days,
            'next_14_days' => $activeTasks->filter(function ($task) use ($next7Days, $next14Days) {
                $effectiveDueDate = $task->getEffectiveDueDate();
                return $effectiveDueDate 
                    && $effectiveDueDate->greaterThanOrEqualTo($next7Days) 
                    && $effectiveDueDate->lessThan($next14Days);
            })->count(),
            'later' => $activeTasks->filter(function ($task) use ($next14Days) {
                $effectiveDueDate = $task->getEffectiveDueDate();
                return $effectiveDueDate && $effectiveDueDate->greaterThanOrEqualTo($next14Days);
            })->count(),
        ];

        // ===== ACTIVE PROJECTS LIST =====
        
        // Group active tasks by project
        $activeProjectsData = $activeTasks->groupBy(function ($task) {
            return $task->column->project_id;
        })->map(function ($tasks, $projectId) {
            $firstTask = $tasks->first();
            return [
                'project' => $firstTask->column->project,
                'active_task_count' => $tasks->count(),
            ];
        })->sortByDesc('active_task_count')->values();

        return view('users.show', compact(
            'user',
            // Current workload
            'totalActiveTasks',
            'inProgressTasks',
            'overdueTasks',
            'dueNext7Days',
            'distinctActiveProjects',
            'tasksWithoutDueDates',
            // Activity footprint
            'totalTasksAssigned',
            'tasksPlanned',
            'tasksActive',
            'tasksCompleted',
            'distinctProjectsLifetime',
            'totalCommentsMade',
            'totalTasksCreated',
            // Activity snapshot
            'tasksCreatedLast30',
            'tasksUpdatedLast30',
            'commentsPostedLast30',
            'tasksCompletedLast30',
            // Charts
            'statusBreakdown',
            'duePressure',
            // Active projects
            'activeProjectsData'
        ));
    }
}
