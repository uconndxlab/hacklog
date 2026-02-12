<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Task;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeamDashboardController extends Controller
{
    public function index(Request $request)
    {
        // Parse date range from request or set defaults
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay() 
            : Carbon::today();
        
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date'))->endOfDay() 
            : Carbon::today()->addDays(30)->endOfDay();

        // Get all active team members (exclude clients)
        $users = User::where('active', true)
            ->where('role', '!=', 'client')
            ->orderBy('name')
            ->get();

        // Build metrics for each user
        $teamMetrics = $users->map(function ($user) use ($startDate, $endDate) {
            return $this->buildUserMetrics($user, $startDate, $endDate);
        });

        return view('team.dashboard', [
            'teamMetrics' => $teamMetrics,
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
        ]);
    }

    /**
     * Build comprehensive metrics for a single user within the date range.
     * 
     * Date range logic:
     * - Tasks are included if their due_date (or phase end_date fallback) falls within the range
     * - OR if they have activity (created, updated, completed) within the range
     */
    private function buildUserMetrics(User $user, Carbon $startDate, Carbon $endDate)
    {
        // Get all tasks assigned to this user
        $allUserTasks = $user->tasks()
            ->with(['phase', 'column'])
            ->get();

        // Filter tasks to those relevant to the date range
        $tasksInRange = $allUserTasks->filter(function ($task) use ($startDate, $endDate) {
            // Get effective due date (task due_date or phase end_date)
            $effectiveDueDate = $task->getEffectiveDueDate();
            
            // Include if due date is within range
            if ($effectiveDueDate) {
                $dueDate = Carbon::parse($effectiveDueDate);
                if ($dueDate->between($startDate, $endDate)) {
                    return true;
                }
            }

            // Include if completed within range
            if ($task->completed_at) {
                $completedAt = Carbon::parse($task->completed_at);
                if ($completedAt->between($startDate, $endDate)) {
                    return true;
                }
            }

            // Include if created within range
            if ($task->created_at) {
                $createdAt = Carbon::parse($task->created_at);
                if ($createdAt->between($startDate, $endDate)) {
                    return true;
                }
            }

            return false;
        });

        // Basic summary numbers
        $totalTasks = $tasksInRange->count();
        $openTasks = $tasksInRange->where('status', 'planned')->count();
        $inProgressTasks = $tasksInRange->where('status', 'active')->count();
        $completedTasks = $tasksInRange->where('status', 'completed')->count();
        
        // Distinct projects (via phase -> project relationship)
        $distinctProjects = $tasksInRange
            ->pluck('phase.project_id')
            ->filter()
            ->unique()
            ->count();

        // Get detailed project information for tasks in range
        $projectDetails = $tasksInRange
            ->filter(fn($task) => $task->phase && $task->phase->project)
            ->groupBy('phase.project_id')
            ->map(function ($tasks) {
                $firstTask = $tasks->first();
                return [
                    'id' => $firstTask->phase->project->id,
                    'name' => $firstTask->phase->project->name,
                    'active_task_count' => $tasks->whereIn('status', ['planned', 'active'])->count(),
                    'total_task_count' => $tasks->count(),
                ];
            })
            ->filter(fn($project) => $project['active_task_count'] > 0)
            ->sortByDesc('active_task_count')
            ->values();

        // Due-date pressure metrics
        $now = Carbon::now();
        $next7Days = $now->copy()->addDays(7);
        $next14Days = $now->copy()->addDays(14);

        $overdueTasks = $tasksInRange->filter(function ($task) use ($now) {
            if ($task->status === 'completed') {
                return false;
            }
            $effectiveDueDate = $task->getEffectiveDueDate();
            return $effectiveDueDate && Carbon::parse($effectiveDueDate)->lt($now);
        })->count();

        $dueNext7Days = $tasksInRange->filter(function ($task) use ($now, $next7Days) {
            if ($task->status === 'completed') {
                return false;
            }
            $effectiveDueDate = $task->getEffectiveDueDate();
            return $effectiveDueDate && Carbon::parse($effectiveDueDate)->between($now, $next7Days);
        })->count();

        $dueNext14Days = $tasksInRange->filter(function ($task) use ($next7Days, $next14Days) {
            if ($task->status === 'completed') {
                return false;
            }
            $effectiveDueDate = $task->getEffectiveDueDate();
            return $effectiveDueDate && Carbon::parse($effectiveDueDate)->between($next7Days, $next14Days);
        })->count();

        $dueLater = $tasksInRange->filter(function ($task) use ($next14Days) {
            if ($task->status === 'completed') {
                return false;
            }
            $effectiveDueDate = $task->getEffectiveDueDate();
            return $effectiveDueDate && Carbon::parse($effectiveDueDate)->gt($next14Days);
        })->count();

        // Completion adherence (for completed tasks only)
        $completedTasksList = $tasksInRange->where('status', 'completed');
        $completedOnTime = 0;
        $completedLate = 0;

        foreach ($completedTasksList as $task) {
            $effectiveDueDate = $task->getEffectiveDueDate();
            if ($effectiveDueDate) {
                $dueDate = Carbon::parse($effectiveDueDate);
                
                // Use completed_at if available, otherwise fall back to updated_at
                $completedAt = $task->completed_at 
                    ? Carbon::parse($task->completed_at) 
                    : Carbon::parse($task->updated_at);
                
                if ($completedAt->lte($dueDate)) {
                    $completedOnTime++;
                } else {
                    $completedLate++;
                }
            }
        }

        $totalCompletedWithDueDate = $completedOnTime + $completedLate;
        $percentOnTime = $totalCompletedWithDueDate > 0 
            ? round(($completedOnTime / $totalCompletedWithDueDate) * 100) 
            : null;
        $percentLate = $totalCompletedWithDueDate > 0 
            ? round(($completedLate / $totalCompletedWithDueDate) * 100) 
            : null;

        return [
            'user' => $user,
            'summary' => [
                'total_tasks' => $totalTasks,
                'open_tasks' => $openTasks,
                'in_progress_tasks' => $inProgressTasks,
                'completed_tasks' => $completedTasks,
                'distinct_projects' => $distinctProjects,
            ],
            'projects' => $projectDetails,
            'due_pressure' => [
                'overdue' => $overdueTasks,
                'next_7_days' => $dueNext7Days,
                'next_14_days' => $dueNext14Days,
                'later' => $dueLater,
            ],
            'adherence' => [
                'percent_on_time' => $percentOnTime,
                'percent_late' => $percentLate,
                'completed_on_time' => $completedOnTime,
                'completed_late' => $completedLate,
            ],
        ];
    }
}
