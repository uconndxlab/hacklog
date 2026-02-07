<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Project::query();

        // Filter by assigned to me (default behavior, unless explicitly disabled)
        $assignedFilter = $request->input('assigned');
        if ($assignedFilter !== 'all') {
            $query->whereHas('epics.tasks.users', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            })->whereHas('epics.tasks', function ($query) {
                $query->where('tasks.status', '!=', 'completed');
            });
        }

        // Filter by status (hide archived by default)
        $statusFilter = $request->input('status');
        if ($statusFilter) {
            $query->where('status', $statusFilter);
        } else {
            // Default: hide archived projects
            $query->where('status', '!=', 'archived');
        }

        $projects = $query->orderBy('created_at', 'desc')->get();

        return view('projects.index', compact('projects'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('projects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,paused,archived',
        ]);

        $project = Project::create($validated);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        // Load epics (exclude completed by default)
        $project->load(['epics' => function ($query) {
            $query->where('status', '!=', 'completed')
                  ->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
                  ->orderBy('start_date', 'asc');
        }, 'columns']);

        // Get upcoming tasks (next 5, ordered by due date)
        $upcomingTasks = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->where('status', '!=', 'completed')
        ->whereNotNull('due_date')
        ->with(['epic', 'column'])
        ->orderByRaw('CASE WHEN due_date < ? THEN 0 ELSE 1 END', [today()])
        ->orderBy('due_date', 'asc')
        ->limit(5)
        ->get();

        // Calculate project health metrics
        $activeEpicsCount = $project->epics()->where('status', '!=', 'completed')->count();
        
        $overdueTasks = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->where('status', '!=', 'completed')
        ->whereNotNull('due_date')
        ->where('due_date', '<', today())
        ->count();

        // Find nearest upcoming due date (task or epic)
        $nearestTaskDate = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->where('status', '!=', 'completed')
        ->whereNotNull('due_date')
        ->where('due_date', '>=', today())
        ->orderBy('due_date', 'asc')
        ->value('due_date');

        $nearestEpicDate = $project->epics()
            ->where('status', '!=', 'completed')
            ->whereNotNull('end_date')
            ->where('end_date', '>=', today())
            ->orderBy('end_date', 'asc')
            ->value('end_date');

        $nearestDueDate = null;
        if ($nearestTaskDate && $nearestEpicDate) {
            $nearestDueDate = $nearestTaskDate->isBefore($nearestEpicDate) ? $nearestTaskDate : $nearestEpicDate;
        } elseif ($nearestTaskDate) {
            $nearestDueDate = $nearestTaskDate;
        } elseif ($nearestEpicDate) {
            $nearestDueDate = $nearestEpicDate;
        }

        return view('projects.show', compact('project', 'upcomingTasks', 'activeEpicsCount', 'overdueTasks', 'nearestDueDate'));
    }

    /**
     * Display the project kanban board with all tasks across epics
     */
    public function board(Request $request, Project $project)
    {
        // If no epic filter is specified, redirect to first active epic
        if (!$request->has('epic') || !$request->epic) {
            $firstActiveEpic = $project->epics()
                ->where('status', 'active')
                ->orderBy('name')
                ->first();
            
            // If there's an active epic, redirect to it
            if ($firstActiveEpic) {
                return redirect()->route('projects.board', [
                    'project' => $project,
                    'epic' => $firstActiveEpic->id
                ]);
            }
            
            // Otherwise, try to find the first planned epic
            $firstPlannedEpic = $project->epics()
                ->where('status', 'planned')
                ->orderBy('name')
                ->first();
            
            if ($firstPlannedEpic) {
                return redirect()->route('projects.board', [
                    'project' => $project,
                    'epic' => $firstPlannedEpic->id
                ]);
            }
            
            // If no active or planned epics, fall through to show all
        }
        
        // Load columns ordered by position
        $columns = $project->columns()->orderBy('position')->get();
        
        // Load epics for filter dropdown
        $epics = $project->epics()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        
        // Build task query
        $tasksQuery = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        });
        
        // Apply epic filter if provided
        if ($request->has('epic') && $request->epic) {
            $tasksQuery->where('epic_id', $request->epic);
        }

        // Apply "assigned to me" filter if requested
        if ($request->query('assigned') === 'me') {
            $tasksQuery->whereHas('users', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            });
        }
        
        // Load all tasks for this project (optionally filtered by epic)
        // Eager load epic and users relationships and order by position within each column
        $tasks = $tasksQuery->with(['epic', 'users'])->get()->groupBy('column_id');
        
        return view('projects.board', compact('project', 'columns', 'tasks', 'epics'));
    }

    /**
     * Return task creation form for board modal
     */
    public function taskForm(Request $request, Project $project)
    {
        $columnId = $request->query('column');
        $epics = $project->epics()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        $users = \App\Models\User::orderBy('name')->get();
        
        return view('projects.partials.board-task-form', compact('project', 'columnId', 'epics', 'users'));
    }

    /**
     * Return task edit form for board modal
     */
    public function editTask(Project $project, \App\Models\Task $task)
    {
        // Verify task belongs to this project
        if ($task->epic->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        $epics = $project->epics()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        $columns = $project->columns;
        $users = \App\Models\User::orderBy('name')->get();
        $task->load('users');
        
        return view('projects.partials.board-task-form', compact('project', 'task', 'epics', 'columns', 'users'));
    }

    /**
     * Show task details for board modal
     */
    public function showTask(Project $project, \App\Models\Task $task)
    {
        // Verify task belongs to this project
        if ($task->epic->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        $task->load(['epic', 'column', 'users']);
        
        return view('projects.partials.board-task-details', compact('project', 'task'));
    }

    /**
     * Store a task from the board modal
     */
    public function storeTask(Request $request, Project $project)
    {
        $validated = $request->validate([
            'epic_id' => 'required|exists:epics,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Find the epic and verify it belongs to this project
        $epic = \App\Models\Epic::findOrFail($validated['epic_id']);
        if ($epic->project_id !== $project->id) {
            abort(403, 'Epic does not belong to this project.');
        }

        // Set position to end of column
        $validated['position'] = \App\Models\Task::getNextPositionInColumn($validated['column_id']);

        // Create the task
        $task = $epic->tasks()->create($validated);

        // Sync assignees
        $task->users()->sync($validated['assignees'] ?? []);

        // Check if this is from the modal with HTMX
        $fromBoardModal = $request->input('from_board_modal');
        
        if (request()->header('HX-Request') && $fromBoardModal) {
            // HTMX: Return updated column task list
            $columns = $project->columns;
            $tasks = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->with(['epic', 'users'])
            ->get()
            ->groupBy('column_id');
            
            $column = $columns->firstWhere('id', $validated['column_id']);
            
            // Return the updated column with a script to close the modal
            $html = view('projects.partials.board-column-tasks', [
                'column' => $column,
                'columnTasks' => $tasks->get($validated['column_id'], collect()),
                'project' => $project,
                'allColumns' => $columns,
                'isProjectBoard' => true
            ])->render();
            
            // Add Bootstrap modal close trigger
            $html .= '<script>bootstrap.Modal.getInstance(document.getElementById("taskModal")).hide();</script>';
            
            return response($html);
        }

        // Regular form submission: redirect to board
        return redirect()->route('projects.board', $project)
            ->with('success', 'Task created successfully.');
    }

    /**
     * Store a task from the task create form (with selectable epic)
     */
    public function storeProjectTask(Request $request, Project $project)
    {
        $validated = $request->validate([
            'epic_id' => 'required|exists:epics,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Find the epic and verify it belongs to this project
        $epic = \App\Models\Epic::findOrFail($validated['epic_id']);
        if ($epic->project_id !== $project->id) {
            abort(403, 'Epic does not belong to this project.');
        }

        // Set position to end of column
        $validated['position'] = \App\Models\Task::getNextPositionInColumn($validated['column_id']);

        // Create the task
        $task = $epic->tasks()->create($validated);

        // Sync assignees
        $task->users()->sync($validated['assignees'] ?? []);

        return redirect()->route('projects.epics.tasks.show', [$project, $epic, $task])
            ->with('success', 'Task created successfully.');
    }

    /**
     * Update a task from the board modal
     */
    public function updateTask(Request $request, Project $project, \App\Models\Task $task)
    {
        // Verify task belongs to this project
        if ($task->epic->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        $validated = $request->validate([
            'epic_id' => 'required|exists:epics,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Verify the new epic belongs to this project
        $epic = \App\Models\Epic::findOrFail($validated['epic_id']);
        if ($epic->project_id !== $project->id) {
            abort(403, 'Epic does not belong to this project.');
        }

        $oldColumnId = $task->column_id;
        
        // Update the task
        $task->update($validated);

        // Sync assignees
        $task->users()->sync($validated['assignees'] ?? []);

        // If column changed, adjust positions
        if ($oldColumnId != $validated['column_id']) {
            $task->position = \App\Models\Task::getNextPositionInColumn($validated['column_id']);
            $task->save();
        }

        // Check if this is from the modal with HTMX
        $fromBoardModal = $request->input('from_board_modal');
        
        if (request()->header('HX-Request') && $fromBoardModal) {
            // HTMX: Return updated column task lists
            $columns = $project->columns;
            $tasks = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->with(['epic', 'users'])
            ->get()
            ->groupBy('column_id');
            
            // If column changed, we need to update both old and new columns
            if ($oldColumnId != $validated['column_id']) {
                $oldColumn = $columns->firstWhere('id', $oldColumnId);
                $newColumn = $columns->firstWhere('id', $validated['column_id']);
                
                // Return both column updates with modal close script
                $html = view('projects.partials.board-column-tasks', [
                    'column' => $oldColumn,
                    'columnTasks' => $tasks->get($oldColumnId, collect()),
                    'project' => $project,
                    'allColumns' => $columns,
                    'isProjectBoard' => true
                ])->render();
                
                $html .= '<div id="board-column-' . $validated['column_id'] . '-tasks" hx-swap-oob="true">';
                $html .= view('projects.partials.board-column-tasks', [
                    'column' => $newColumn,
                    'columnTasks' => $tasks->get($validated['column_id'], collect()),
                    'project' => $project,
                    'allColumns' => $columns,
                    'isProjectBoard' => true
                ])->render();
                $html .= '</div>';
                
                $html .= '<script>bootstrap.Modal.getInstance(document.getElementById("taskModal")).hide();</script>';
                
                return response($html);
            } else {
                // Same column, just update it
                $column = $columns->firstWhere('id', $validated['column_id']);
                
                $html = view('projects.partials.board-column-tasks', [
                    'column' => $column,
                    'columnTasks' => $tasks->get($validated['column_id'], collect()),
                    'project' => $project,
                    'allColumns' => $columns,
                    'isProjectBoard' => true
                ])->render();
                
                $html .= '<script>bootstrap.Modal.getInstance(document.getElementById("taskModal")).hide();</script>';
                
                return response($html);
            }
        }

        // Regular form submission: redirect to board
        return redirect()->route('projects.board', $project)
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        return view('projects.edit', compact('project'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,paused,archived',
        ]);

        $project->update($validated);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    /**
     * Display the schedule view for a project.
     */
    public function schedule(Request $request, Project $project)
    {
        $showCompleted = $request->query('show_completed', '0') === '1';

        // Load epics with their tasks, eager loading columns for task display
        // Tasks are sorted by due_date (nulls last) within each epic
        $project->load(['epics' => function ($query) use ($showCompleted, $request) {
            if (!$showCompleted) {
                $query->where('status', '!=', 'completed');
            }
            $query->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
                  ->orderBy('start_date', 'asc')
                  ->with(['tasks' => function ($taskQuery) use ($request) {
                      $taskQuery->with('column')
                                ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                                ->orderBy('due_date', 'asc');
                      
                      // Filter by assigned user
                      if ($request->input('assigned') === 'me') {
                          $taskQuery->whereHas('users', function ($query) use ($request) {
                              $query->where('users.id', $request->user()->id);
                          });
                      }
                  }]);
        }]);

        return view('projects.schedule', compact('project', 'showCompleted'));
    }

    /**
     * Display the timeline view for a project.
     */
    public function timeline(Request $request, Project $project)
    {
        $showCompleted = $request->query('show_completed', '0') === '1';

        // Get epics that have at least one date
        $epicsQuery = $project->epics()
            ->where(function ($query) {
                $query->whereNotNull('start_date')
                      ->orWhereNotNull('end_date');
            });
        
        if (!$showCompleted) {
            $epicsQuery->where('status', '!=', 'completed');
        }
        
        $epics = $epicsQuery
            ->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date', 'asc')
            ->orderBy('end_date', 'asc')
            ->get();

        // If no epics with dates, return early
        if ($epics->isEmpty()) {
            return view('projects.timeline', [
                'project' => $project,
                'epics' => $epics,
                'weeks' => [],
                'timelineStart' => null,
                'timelineEnd' => null,
                'tooWide' => false,
            ]);
        }

        // Calculate timeline window - use a smart range that focuses on current/future work
        // Only use non-completed epics for date range calculation
        $allDates = [];
        foreach ($epics as $epic) {
            if ($epic->status === 'completed') {
                continue;
            }
            if ($epic->start_date) {
                $allDates[] = $epic->start_date;
            }
            if ($epic->end_date) {
                $allDates[] = $epic->end_date;
            }
        }

        // If no active epic dates, use all dates for calculation
        if (empty($allDates)) {
            foreach ($epics as $epic) {
                if ($epic->start_date) {
                    $allDates[] = $epic->start_date;
                }
                if ($epic->end_date) {
                    $allDates[] = $epic->end_date;
                }
            }
        }

        $minDate = min($allDates);
        $maxDate = max($allDates);
        $today = \Carbon\Carbon::today();

        // Smart timeline window: 
        // - If all dates are in the future, start from earliest date
        // - If all dates are in the past, start from earliest (historical view)
        // - If mixed, start from 4 weeks ago or earliest date (whichever is later)
        if ($minDate->isFuture()) {
            $timelineStart = $minDate->copy()->startOfWeek();
        } elseif ($maxDate->isPast()) {
            $timelineStart = $minDate->copy()->startOfWeek();
        } else {
            $fourWeeksAgo = $today->copy()->subWeeks(4)->startOfWeek();
            $timelineStart = $minDate->isBefore($fourWeeksAgo) ? $fourWeeksAgo : $minDate->copy()->startOfWeek();
        }

        // End date: use max date or extend to 4 weeks in future, whichever is later
        $fourWeeksFromNow = $today->copy()->addWeeks(4)->endOfWeek();
        $timelineEnd = $maxDate->isAfter($fourWeeksFromNow) ? $maxDate->copy()->endOfWeek() : $fourWeeksFromNow;
        
        // Calculate number of weeks
        $weekCount = $timelineStart->diffInWeeks($timelineEnd) + 1;
        $tooWide = $weekCount > 26;

        // Generate weeks array (limit to 26 weeks if too wide)
        $weeks = [];
        $currentWeek = $timelineStart->copy();
        $displayWeeks = min($weekCount, 26);
        
        for ($i = 0; $i < $displayWeeks; $i++) {
            $weeks[] = [
                'start' => $currentWeek->copy(),
                'label' => $currentWeek->format('M j'),
            ];
            $currentWeek->addWeek();
        }

        return view('projects.timeline', compact('project', 'epics', 'weeks', 'timelineStart', 'timelineEnd', 'tooWide', 'showCompleted'));
    }
}
