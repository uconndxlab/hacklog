<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    /**
     * Display the organization-wide schedule.
     * 
     * Shows tasks across all projects grouped by due date.
     * Default range: today to today + 30 days
     * Overdue tasks (due_date < today, status != completed) appear first.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Parse date filters with defaults
        $filterStart = $request->has('start') 
            ? Carbon::parse($request->input('start')) 
            : Carbon::today();
        
        $filterEnd = $request->has('end') 
            ? Carbon::parse($request->input('end')) 
            : Carbon::today()->addDays(30);
        
        $showCompleted = $request->has('show_completed') && $request->input('show_completed') === '1';

        // Get visible project IDs for this user
        $visibleProjectIds = Project::visibleTo($user)->pluck('id');

        // Get all visible projects for filter dropdown
        $projects = Project::visibleTo($user)->orderBy('name')->get();

        // Get all active users for assignee filter
        $users = User::where('active', true)->orderBy('name')->get();

        // Build task query with eager loading to avoid N+1
        // Include tasks with explicit due_date OR tasks that can inherit from phase end_date
        $tasksQuery = Task::query()
            ->with(['phase.project', 'column', 'users'])
            ->whereHas('column.project', function ($q) use ($visibleProjectIds) {
                // Only show tasks from visible projects
                $q->whereIn('project_id', $visibleProjectIds);
            })
            ->where(function ($query) {
                // Tasks with explicit due_date
                $query->whereNotNull('due_date')
                      // OR tasks without due_date but phase has end_date (will inherit)
                      ->orWhereHas('phase', function ($q) {
                          $q->whereNotNull('end_date');
                      });
            });
        
        // Filter by status
        if (!$showCompleted) {
            $tasksQuery->where('status', '!=', 'completed');
        }

        // Filter by project
        if ($request->filled('project_id')) {
            $tasksQuery->whereHas('column.project', function ($q) use ($request) {
                $q->where('id', $request->input('project_id'));
            });
        }

        // Filter by assignee
        if ($request->filled('assignee')) {
            $assignee = $request->input('assignee');
            if ($assignee === 'unassigned') {
                $tasksQuery->whereDoesntHave('users');
            } else {
                $tasksQuery->whereHas('users', function ($query) use ($assignee) {
                    $query->where('users.id', $assignee);
                });
            }
        }

        // Get all tasks (we'll separate overdue vs. in-range in PHP)
        $allTasks = $tasksQuery->orderBy('due_date', 'asc')->get();

        // Separate overdue from in-range tasks using effective due date
        // Effective due date = task.due_date ?? phase.end_date
        $today = Carbon::today();
        $overdueTasks = collect();
        $rangeTasks = collect();

        foreach ($allTasks as $task) {
            $effectiveDueDate = $task->getEffectiveDueDate();
            
            // Skip tasks with no effective due date
            if (!$effectiveDueDate) {
                continue;
            }
            
            // Check if overdue based on effective date
            $isOverdue = $effectiveDueDate->isBefore($today) && $task->status !== 'completed';
            
            if ($isOverdue) {
                $overdueTasks->push($task);
            } elseif ($effectiveDueDate >= $filterStart && $effectiveDueDate <= $filterEnd) {
                // Within date range
                $rangeTasks->push($task);
            }
        }

        // Group tasks by effective due date for display
        $overdueGrouped = $overdueTasks->groupBy(function ($task) {
            return $task->getEffectiveDueDate()->format('Y-m-d');
        })->sortKeys();

        $rangeGrouped = $rangeTasks->groupBy(function ($task) {
            return $task->getEffectiveDueDate()->format('Y-m-d');
        })->sortKeys();

        return view('schedule.index', compact(
            'overdueGrouped',
            'rangeGrouped',
            'filterStart',
            'filterEnd',
            'showCompleted',
            'projects',
            'users'
        ));
    }
}
