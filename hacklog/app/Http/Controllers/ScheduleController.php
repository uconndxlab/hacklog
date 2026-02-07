<?php

namespace App\Http\Controllers;

use App\Models\Task;
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
        // Parse date filters with defaults
        $filterStart = $request->has('start') 
            ? Carbon::parse($request->input('start')) 
            : Carbon::today();
        
        $filterEnd = $request->has('end') 
            ? Carbon::parse($request->input('end')) 
            : Carbon::today()->addDays(30);
        
        $showCompleted = $request->has('show_completed') && $request->input('show_completed') === '1';

        // Build task query with eager loading to avoid N+1
        // Include tasks with explicit due_date OR tasks that can inherit from epic end_date
        $tasksQuery = Task::query()
            ->with(['epic.project', 'column', 'users'])
            ->where(function ($query) {
                // Tasks with explicit due_date
                $query->whereNotNull('due_date')
                      // OR tasks without due_date but epic has end_date (will inherit)
                      ->orWhereHas('epic', function ($q) {
                          $q->whereNotNull('end_date');
                      });
            });
        
        // Filter by status
        if (!$showCompleted) {
            $tasksQuery->where('status', '!=', 'completed');
        }

        // Filter by assigned user
        if ($request->input('assigned') === 'me') {
            $tasksQuery->whereHas('users', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            });
        }

        // Get all tasks (we'll separate overdue vs. in-range in PHP)
        $allTasks = $tasksQuery->orderBy('due_date', 'asc')->get();

        // Separate overdue from in-range tasks using effective due date
        // Effective due date = task.due_date ?? epic.end_date
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
            'showCompleted'
        ));
    }
}
