<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Show the user dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $today = today();

        // Get all assigned tasks (not completed) with relationships
        $allAssignedTasks = Task::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->where('status', '!=', 'completed')
        ->with(['phase.project', 'column'])
        ->get();

        // Group assigned tasks by priority
        $overdueTasks = $allAssignedTasks->filter(function($task) use ($today) {
            $effectiveDueDate = $task->getEffectiveDueDate();
            return $effectiveDueDate && $effectiveDueDate->isBefore($today);
        })->sortBy(function($task) {
            return $task->getEffectiveDueDate();
        });

        $dueThisWeek = $allAssignedTasks->filter(function($task) use ($today) {
            $effectiveDueDate = $task->getEffectiveDueDate();
            $weekEnd = $today->copy()->endOfWeek();
            return $effectiveDueDate && 
                   $effectiveDueDate->gte($today) && 
                   $effectiveDueDate->lte($weekEnd);
        })->sortBy(function($task) {
            return $task->getEffectiveDueDate();
        });

        $dueNext = $allAssignedTasks->filter(function($task) use ($today) {
            $effectiveDueDate = $task->getEffectiveDueDate();
            $weekEnd = $today->copy()->endOfWeek();
            return $effectiveDueDate && $effectiveDueDate->gt($weekEnd);
        })->sortBy(function($task) {
            return $task->getEffectiveDueDate();
        })->take(10);

        $noDueDate = $allAssignedTasks->filter(function($task) {
            return !$task->getEffectiveDueDate();
        })->take(5);

        // Recently active tasks (updated in last 7 days by the user)
        $recentlyActive = Task::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->where('updated_at', '>=', now()->subDays(7))
        ->where('status', '!=', 'completed')
        ->with(['phase.project', 'column'])
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();

        // Unassigned tasks from active projects
        $unassignedTasks = Task::whereDoesntHave('users')
            ->where('status', '!=', 'completed')
            ->whereHas('phase', function($query) {
                $query->whereHas('project', function($projectQuery) {
                    $projectQuery->where('status', 'active');
                });
            })
            ->with(['phase.project', 'column'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Projects you're on: where user has tasks OR is a resource OR directly shared
        // This applies to all users (clients, team, admin)
        $activeProjects = \App\Models\Project::visibleTo($user)
            ->where('status', 'active')
            ->where(function($q) use ($user) {
                // Has tasks assigned
                $q->whereHas('phases.tasks.users', function ($taskQuery) use ($user) {
                    $taskQuery->where('users.id', $user->id);
                })
                // OR is a project resource (contributor, manager, viewer)
                ->orWhereHas('resources', function ($resourceQuery) use ($user) {
                    $resourceQuery->where('user_id', $user->id);
                })
                // OR project is directly shared with this user (not via role)
                ->orWhereHas('shares', function ($shareQuery) use ($user) {
                    $shareQuery->where('shareable_type', 'user')
                               ->where('shareable_id', (string)$user->id);
                });
            })
            ->with(['phases' => function($q) {
                $q->where('status', '!=', 'completed');
            }])
            ->orderBy('name')
            ->get()
            ->map(function($project) use ($user) {
                // Count active tasks assigned to user
                $taskCount = Task::whereHas('phase', function($q) use ($project) {
                    $q->where('project_id', $project->id);
                })
                ->whereHas('users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->where('status', '!=', 'completed')
                ->count();
                
                // Find next phase date
                $nextEpicDate = $project->phases
                    ->filter(fn($phase) => $phase->end_date && $phase->end_date->gte(today()))
                    ->sortBy('end_date')
                    ->first()?->end_date;
                
                $project->user_task_count = $taskCount;
                $project->next_epic_date = $nextEpicDate;
                
                return $project;
            });

        return view('dashboard', compact(
            'overdueTasks',
            'dueThisWeek', 
            'dueNext', 
            'noDueDate',
            'recentlyActive',
            'unassignedTasks',
            'activeProjects'
        ));
    }
}