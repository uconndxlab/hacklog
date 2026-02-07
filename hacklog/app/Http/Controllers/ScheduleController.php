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
        $tasksQuery = Task::query()
            ->with(['epic.project', 'column', 'users'])
            ->whereNotNull('due_date');
        
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

        // Separate overdue from in-range tasks
        $today = Carbon::today();
        $overdueTasks = collect();
        $rangeTasks = collect();

        foreach ($allTasks as $task) {
            if ($task->isOverdue()) {
                // Overdue: due_date < today AND status != completed
                $overdueTasks->push($task);
            } elseif ($task->due_date >= $filterStart && $task->due_date <= $filterEnd) {
                // Within date range
                $rangeTasks->push($task);
            }
        }

        // Group tasks by due date for display
        $overdueGrouped = $overdueTasks->groupBy(function ($task) {
            return $task->due_date->format('Y-m-d');
        })->sortKeys();

        $rangeGrouped = $rangeTasks->groupBy(function ($task) {
            return $task->due_date->format('Y-m-d');
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
