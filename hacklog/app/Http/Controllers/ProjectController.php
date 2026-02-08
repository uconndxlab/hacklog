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
        $user = $request->user();
        
        // Start with visibility-filtered projects
        $query = Project::visibleTo($user)
            ->with(['epics' => function($q) {
                $q->where('status', '!=', 'completed');
            }]);

        // Default filters for non-admin users
        // Clients default to 'all' (they only see shared projects anyway)
        // Team members default to 'assigned' to reduce noise
        $defaultScope = $user->isClient() ? 'all' : ($user->isAdmin() ? 'all' : 'assigned');
        $scope = $request->input('scope', $defaultScope);
        $status = $request->input('status', 'active');
        $timeFilter = $request->input('time');
        $search = $request->input('search');
        $ownerFilter = $request->input('owner'); // Admin only

        // Scope filter: All / Assigned to me / I'm a contributor / Projects I'm on
        if ($scope === 'assigned') {
            // Projects where user has assigned tasks (not completed)
            $query->whereHas('epics.tasks', function ($q) use ($user) {
                $q->where('status', '!=', 'completed')
                  ->whereHas('users', function ($userQuery) use ($user) {
                      $userQuery->where('users.id', $user->id);
                  });
            });
        } elseif ($scope === 'contributor') {
            // Projects where user has ANY tasks (completed or not)
            $query->whereHas('epics.tasks.users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        } elseif ($scope === 'member') {
            // Projects where user has tasks OR is a project resource OR directly shared
            $query->where(function($q) use ($user) {
                // Has tasks assigned
                $q->whereHas('epics.tasks.users', function ($taskQuery) use ($user) {
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
            });
        }
        // 'all' scope - no filtering (but still respects visibility)

        // Status filter: active (default), paused, completed, etc.
        if ($status && in_array($status, ['planned', 'active', 'paused', 'completed', 'archived'])) {
            $query->where('status', $status);
        }

        // Time-based filter: Due in 7/14/30 days, Overdue
        if ($timeFilter) {
            $today = \Carbon\Carbon::today();
            
            if ($timeFilter === 'overdue') {
                // Projects with overdue tasks or epics
                $query->where(function($q) use ($today) {
                    $q->whereHas('epics', function($epicQuery) use ($today) {
                        $epicQuery->where('end_date', '<', $today)
                                  ->where('status', '!=', 'completed');
                    })->orWhereHas('epics.tasks', function($taskQuery) use ($today) {
                        $taskQuery->where('status', '!=', 'completed')
                                  ->where(function($dateQuery) use ($today) {
                                      // Task explicit due_date or inherited from epic
                                      $dateQuery->where('due_date', '<', $today)
                                                ->orWhereHas('epic', function($epicQ) use ($today) {
                                                    $epicQ->where('end_date', '<', $today)
                                                          ->whereNull('tasks.due_date');
                                                });
                                  });
                    });
                });
            } elseif (in_array($timeFilter, ['7', '14', '30'])) {
                $daysAhead = (int) $timeFilter;
                $futureDate = $today->copy()->addDays($daysAhead);
                
                // Projects with tasks/epics due within timeframe
                $query->where(function($q) use ($today, $futureDate) {
                    $q->whereHas('epics', function($epicQuery) use ($today, $futureDate) {
                        $epicQuery->whereBetween('end_date', [$today, $futureDate])
                                  ->where('status', '!=', 'completed');
                    })->orWhereHas('epics.tasks', function($taskQuery) use ($today, $futureDate) {
                        $taskQuery->where('status', '!=', 'completed')
                                  ->where(function($dateQuery) use ($today, $futureDate) {
                                      // Task explicit due_date or inherited from epic
                                      $dateQuery->whereBetween('due_date', [$today, $futureDate])
                                                ->orWhereHas('epic', function($epicQ) use ($today, $futureDate) {
                                                    $epicQ->whereBetween('end_date', [$today, $futureDate])
                                                          ->whereNull('tasks.due_date');
                                                });
                                  });
                    });
                });
            }
        }

        // Admin-only: Filter by project owner/manager
        if ($ownerFilter && $user->isAdmin() && is_numeric($ownerFilter)) {
            $query->whereHas('resources', function($q) use ($ownerFilter) {
                $q->where('user_id', $ownerFilter)
                  ->where('role', 'manager');
            });
        }

        // Search filter: name and description
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        $projects = $query->orderBy('created_at', 'desc')->get();

        // If this is an HTMX request, return only the projects list partial
        if ($request->header('HX-Request')) {
            return view('projects.partials.projects-list', compact('projects'));
        }

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
                return redirect()->route('projects.board', array_merge(
                    [
                        'project' => $project,
                        'epic' => $firstActiveEpic->id
                    ],
                    $request->only(['task', 'assigned'])
                ));
            }
            
            // Otherwise, try to find the first planned epic
            $firstPlannedEpic = $project->epics()
                ->where('status', 'planned')
                ->orderBy('name')
                ->first();
            
            if ($firstPlannedEpic) {
                return redirect()->route('projects.board', array_merge(
                    [
                        'project' => $project,
                        'epic' => $firstPlannedEpic->id
                    ],
                    $request->only(['task', 'assigned'])
                ));
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

        // Apply assignment filter if requested
        $assigned = $request->query('assigned');
        if ($assigned === 'me') {
            // Filter to tasks assigned to current user
            $tasksQuery->whereHas('users', function ($query) use ($request) {
                $query->where('users.id', $request->user()->id);
            });
        } elseif ($assigned === 'none') {
            // Filter to unassigned tasks
            $tasksQuery->whereDoesntHave('users');
        } elseif ($assigned && is_numeric($assigned)) {
            // Filter to tasks assigned to specific user
            $tasksQuery->whereHas('users', function ($query) use ($assigned) {
                $query->where('users.id', $assigned);
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
        // Tasks are sorted by effective due date (explicit due_date or inherited from epic)
        $project->load(['epics' => function ($query) use ($showCompleted, $request) {
            if (!$showCompleted) {
                $query->where('status', '!=', 'completed');
            }
            $query->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
                  ->orderBy('start_date', 'asc')
                  ->with(['tasks' => function ($taskQuery) use ($request, $showCompleted) {
                      $taskQuery->with('column', 'users');
                      
                      // Only show tasks with effective due dates (explicit or inherited)
                      // This will be handled by eager loading, we'll filter in the view
                      
                      // Filter by assigned user
                      if ($request->input('assigned') === 'me') {
                          $taskQuery->whereHas('users', function ($query) use ($request) {
                              $query->where('users.id', $request->user()->id);
                          });
                      }
                      
                      if (!$showCompleted) {
                          $taskQuery->where('status', '!=', 'completed');
                      }
                      
                      // Sort by explicit due_date first, then position
                      $taskQuery->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                                ->orderBy('due_date', 'asc');
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
            $weekStart = $currentWeek->copy();
            $weekEnd = $currentWeek->copy()->endOfWeek();
            
            // Format label as date range
            if ($weekStart->month === $weekEnd->month) {
                $label = $weekStart->format('M j') . '-' . $weekEnd->format('j');
            } else {
                $label = $weekStart->format('M j') . ' - ' . $weekEnd->format('M j');
            }
            
            $weeks[] = [
                'start' => $weekStart,
                'end' => $weekEnd,
                'label' => $label,
                'due_count' => 0, // Will be populated below
            ];
            $currentWeek->addWeek();
        }

        // Aggregate due dates per week
        // Count epic end_date and task due_date occurrences per week
        // Tasks use "effective due date" - explicit due_date or epic end_date fallback
        foreach ($weeks as $index => $week) {
            $dueDateCount = 0;
            
            // Count epic end dates in this week
            foreach ($epics as $epic) {
                if ($epic->end_date && 
                    $epic->end_date->gte($week['start']) && 
                    $epic->end_date->lte($week['end'])) {
                    $dueDateCount++;
                }
            }
            
            // Count task effective due dates in this week
            // Includes tasks with explicit due_date OR tasks inheriting from epic end_date
            $tasks = \App\Models\Task::with('epic')
                ->whereHas('epic', function ($q) use ($epics) {
                    $q->whereIn('id', $epics->pluck('id'));
                })
                ->when(!$showCompleted, function ($q) {
                    $q->where('status', '!=', 'completed');
                })
                ->get();
            
            foreach ($tasks as $task) {
                $effectiveDueDate = $task->getEffectiveDueDate();
                if ($effectiveDueDate && 
                    $effectiveDueDate->gte($week['start']) && 
                    $effectiveDueDate->lte($week['end'])) {
                    $dueDateCount++;
                }
            }
            
            $weeks[$index]['due_count'] = $dueDateCount;
        }

        return view('projects.timeline', compact('project', 'epics', 'weeks', 'timelineStart', 'timelineEnd', 'tooWide', 'showCompleted'));
    }

    /**
     * Show project sharing settings.
     * Displays who can see the project and allows managing shares.
     */
    public function sharing(Project $project)
    {
        // Get all shares with related users
        $shares = $project->shares()->get()->map(function ($share) {
            if ($share->isUserShare()) {
                $share->user = $share->getUser();
            }
            return $share;
        });

        // Get all users for sharing UI (excluding already shared users)
        $sharedUserIds = $shares->where('shareable_type', 'user')
            ->pluck('shareable_id')
            ->toArray();
        
        $availableUsers = \App\Models\User::where('active', true)
            ->whereNotIn('id', $sharedUserIds)
            ->orderBy('name')
            ->get();

        // Check which roles are already shared
        $sharedRoles = $shares->where('shareable_type', 'role')
            ->pluck('shareable_id')
            ->toArray();

        return view('projects.sharing', compact('project', 'shares', 'availableUsers', 'sharedRoles'));
    }

    /**
     * Add a share to the project.
     */
    public function shareStore(Request $request, Project $project)
    {
        $validated = $request->validate([
            'shareable_type' => 'required|in:user,role',
            'shareable_id' => 'required|string',
        ]);

        // Validate shareable_id based on type
        if ($validated['shareable_type'] === 'user') {
            $user = \App\Models\User::find($validated['shareable_id']);
            if (!$user) {
                return back()->withErrors(['shareable_id' => 'User not found']);
            }
        } elseif ($validated['shareable_type'] === 'role') {
            if (!in_array($validated['shareable_id'], ['team', 'client'])) {
                return back()->withErrors(['shareable_id' => 'Invalid role']);
            }
        }

        // Create share (will be ignored if duplicate due to unique constraint)
        try {
            $project->shares()->create($validated);
            $message = $validated['shareable_type'] === 'user' 
                ? 'Project shared with user successfully.'
                : 'Project shared with role successfully.';
            return back()->with('success', $message);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->errorInfo[1] == 1062 || $e->errorInfo[1] == 19) { // MySQL or SQLite duplicate error
                return back()->with('info', 'Already shared with this user or role.');
            }
            throw $e;
        }
    }

    /**
     * Remove a share from the project.
     */
    public function shareDestroy(Project $project, $shareId)
    {
        $share = $project->shares()->findOrFail($shareId);
        $share->delete();

        return back()->with('success', 'Share removed successfully.');
    }}