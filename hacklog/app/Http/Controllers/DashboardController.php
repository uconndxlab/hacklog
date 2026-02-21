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

        // Get all assigned tasks with relationships
        // Clients see awaiting_feedback tasks (they need to provide feedback)
        // Team/Admin don't see awaiting_feedback in their upcoming work
        $excludedStatuses = $user->isClient() ? ['completed'] : ['completed', 'awaiting_feedback'];
        
        $allAssignedTasks = Task::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->whereNotIn('status', $excludedStatuses)
        ->with(['phase.project', 'column.project'])
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

        // For clients: get awaiting_feedback tasks from their projects (not necessarily assigned)
        // For admins/team: get all awaiting_feedback tasks across the org
        $awaitingFeedbackTasks = collect();
        if ($user->isClient()) {
            $awaitingFeedbackTasks = Task::where('status', 'awaiting_feedback')
                ->whereHas('phase.project', function($query) use ($user) {
                    $query->whereHas('shares', function($shareQuery) use ($user) {
                        $shareQuery->where('shareable_type', 'user')
                                   ->where('shareable_id', (string)$user->id);
                    })
                    ->orWhereHas('resources', function($resourceQuery) use ($user) {
                        $resourceQuery->where('user_id', $user->id);
                    });
                })
                ->with(['phase.project', 'column'])
                ->orderBy('updated_at', 'desc')
                ->get();
        } else {
            // Admins and team members see all awaiting_feedback tasks across the org
            $awaitingFeedbackTasks = Task::where('status', 'awaiting_feedback')
                ->with(['phase.project', 'column', 'users'])
                ->orderBy('updated_at', 'desc')
                ->get();
        }

        // Favorited projects for dashboard
        $activeProjects = $user->favoriteProjects()
            ->where('status', 'active')
            ->with(['phases' => function($q) {
                $q->where('status', '!=', 'completed');
            }])
            ->orderBy('name')
            ->get()
            ->map(function($project) use ($user, $excludedStatuses) {
                // Count active tasks assigned to user
                $taskCount = Task::whereHas('phase', function($q) use ($project) {
                    $q->where('project_id', $project->id);
                })
                ->whereHas('users', function($q) use ($user) {
                    $q->where('users.id', $user->id);
                })
                ->whereNotIn('status', $excludedStatuses)
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

        // Recent activities - only from projects in activeProjects, excluding current user's own actions
        $recentActivities = collect();
        $activeProjectIds = $activeProjects->pluck('id')->toArray();
        
        if (!empty($activeProjectIds)) {
            // Get recent project activities (excluding current user)
            $projectActivities = \App\Models\ProjectActivity::whereIn('project_id', $activeProjectIds)
                ->where('user_id', '!=', $user->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->with(['project', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get()
                ->map(function($activity) {
                    return (object)[
                        'type' => 'project',
                        'activity' => $activity,
                        'created_at' => $activity->created_at,
                    ];
                });
            
            // Get recent task activities from these projects (excluding current user)
            $taskActivities = \App\Models\TaskActivity::whereHas('task.column', function($query) use ($activeProjectIds) {
                    $query->whereIn('project_id', $activeProjectIds);
                })
                ->where('user_id', '!=', $user->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->with(['task.column.project', 'task.phase', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get()
                ->map(function($activity) {
                    return (object)[
                        'type' => 'task',
                        'activity' => $activity,
                        'created_at' => $activity->created_at,
                    ];
                });
            
            // Get recent task comments from these projects (excluding current user)
            $taskComments = \App\Models\TaskComment::whereHas('task.column', function($query) use ($activeProjectIds) {
                    $query->whereIn('project_id', $activeProjectIds);
                })
                ->where('user_id', '!=', $user->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->with(['task.column.project', 'task.phase', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(15)
                ->get()
                ->map(function($comment) {
                    return (object)[
                        'type' => 'comment',
                        'activity' => $comment,
                        'created_at' => $comment->created_at,
                    ];
                });
            
            // Merge and sort
            $recentActivities = $projectActivities->concat($taskActivities)->concat($taskComments)
                ->sortByDesc('created_at')
                ->take(30);
        }

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

        return view('dashboard', compact(
            'overdueTasks',
            'dueThisWeek', 
            'dueNext', 
            'noDueDate',
            'awaitingFeedbackTasks',
            'recentActivities',
            'unassignedTasks',
            'activeProjects'
        ));
    }
}