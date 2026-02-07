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

        // Get assigned tasks (not completed), with relationships
        // Order by overdue first, then by due_date asc
        $assignedTasks = Task::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->where('status', '!=', 'completed')
        ->with(['epic.project', 'column'])
        ->orderByRaw('CASE WHEN due_date < ? THEN 0 ELSE 1 END', [today()])
        ->orderBy('due_date', 'asc')
        ->limit(10)
        ->get();

        // Get upcoming deadlines (tasks grouped by due date)
        $upcomingDeadlineTasks = Task::whereHas('users', function ($query) use ($user) {
            $query->where('users.id', $user->id);
        })
        ->where('status', '!=', 'completed')
        ->whereNotNull('due_date')
        ->where('due_date', '>=', today())
        ->with(['epic.project'])
        ->orderBy('due_date', 'asc')
        ->limit(15) // Get more tasks to ensure we have tasks for multiple dates
        ->get()
        ->groupBy(function ($task) {
            return $task->due_date->format('Y-m-d');
        })
        ->take(5); // Take only the first 5 dates

        return view('dashboard', compact('assignedTasks', 'upcomingDeadlineTasks'));
    }
}