<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
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
            ->with(['phases.tasks.users', 'columns.tasks.users']); // Eager load tasks in phases and standalone tasks

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
            // Include both phase tasks and standalone tasks
            $query->where(function($q) use ($user) {
                $q->whereHas('phases.tasks', function ($taskQuery) use ($user) {
                    $taskQuery->where('status', '!=', 'completed')
                      ->whereHas('users', function ($userQuery) use ($user) {
                          $userQuery->where('users.id', $user->id);
                      });
                })
                ->orWhereHas('columns.tasks', function ($taskQuery) use ($user) {
                    $taskQuery->where('status', '!=', 'completed')
                      ->whereNull('phase_id') // Only standalone tasks
                      ->whereHas('users', function ($userQuery) use ($user) {
                          $userQuery->where('users.id', $user->id);
                      });
                });
            });
        } elseif ($scope === 'contributor') {
            // Projects where user has ANY tasks (completed or not)
            // Include both phase tasks and standalone tasks
            $query->where(function($q) use ($user) {
                $q->whereHas('phases.tasks.users', function ($taskQuery) use ($user) {
                    $taskQuery->where('users.id', $user->id);
                })
                ->orWhereHas('columns.tasks.users', function ($taskQuery) use ($user) {
                    $taskQuery->where('users.id', $user->id)
                      ->whereNull('phase_id'); // Only standalone tasks
                });
            });
        } elseif ($scope === 'member') {
            // Projects where user has tasks OR is a project resource OR directly shared
            $query->where(function($q) use ($user) {
                // Has tasks assigned (phase or standalone)
                $q->where(function($taskQuery) use ($user) {
                    $taskQuery->whereHas('phases.tasks.users', function ($tq) use ($user) {
                        $tq->where('users.id', $user->id);
                    })
                    ->orWhereHas('columns.tasks.users', function ($tq) use ($user) {
                        $tq->where('users.id', $user->id)
                           ->whereNull('phase_id'); // Only standalone tasks
                    });
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
                // Projects with overdue tasks or phases
                $query->where(function($q) use ($today) {
                    $q->whereHas('phases', function($phaseQuery) use ($today) {
                        $phaseQuery->where('end_date', '<', $today)
                                  ->where('status', '!=', 'completed');
                    })->orWhereHas('phases.tasks', function($taskQuery) use ($today) {
                        $taskQuery->where('status', '!=', 'completed')
                                  ->where(function($dateQuery) use ($today) {
                                      // Task explicit due_date or inherited from phase
                                      $dateQuery->where('due_date', '<', $today)
                                                ->orWhereHas('phase', function($phaseQ) use ($today) {
                                                    $phaseQ->where('end_date', '<', $today)
                                                          ->whereNull('tasks.due_date');
                                                });
                                  });
                    });
                });
            } elseif (in_array($timeFilter, ['7', '14', '30'])) {
                $daysAhead = (int) $timeFilter;
                $futureDate = $today->copy()->addDays($daysAhead);
                
                // Projects with tasks/phases due within timeframe
                $query->where(function($q) use ($today, $futureDate) {
                    $q->whereHas('phases', function($phaseQuery) use ($today, $futureDate) {
                        $phaseQuery->whereBetween('end_date', [$today, $futureDate])
                                  ->where('status', '!=', 'completed');
                    })->orWhereHas('phases.tasks', function($taskQuery) use ($today, $futureDate) {
                        $taskQuery->where('status', '!=', 'completed')
                                  ->where(function($dateQuery) use ($today, $futureDate) {
                                      // Task explicit due_date or inherited from phase
                                      $dateQuery->whereBetween('due_date', [$today, $futureDate])
                                                ->orWhereHas('phase', function($phaseQ) use ($today, $futureDate) {
                                                    $phaseQ->whereBetween('end_date', [$today, $futureDate])
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

        // Limit for modal
        $limit = $request->input('limit');
        if ($limit && is_numeric($limit)) {
            $query = $query->limit($limit);
        }

        // Apply sorting
        $sort = $request->input('sort', 'recent_activity');
        if ($sort === 'alphabetical') {
            $projects = $query->orderBy('name', 'asc')->get();
        } elseif ($sort === 'recent_activity') {
            // Sort by: 1) Projects with user's tasks first, 2) Recent activity
            $projects = $query->orderBy('updated_at', 'desc')->get();
            
            // Partition projects: user has tasks vs. doesn't have tasks
            $userHasTasks = $projects->filter(function($project) use ($user) {
                // Check tasks in phases
                $hasPhaseTask = $project->phases->some(function($phase) use ($user) {
                    return $phase->tasks->some(function($task) use ($user) {
                        return $task->users->contains($user->id);
                    });
                });
                
                // Check standalone tasks (tasks without phases)
                $hasStandaloneTask = $project->columns->some(function($column) use ($user) {
                    return $column->tasks->where('phase_id', null)->some(function($task) use ($user) {
                        return $task->users->contains($user->id);
                    });
                });
                
                return $hasPhaseTask || $hasStandaloneTask;
            })->sortByDesc('updated_at')->values();
            
            $userNoTasks = $projects->reject(function($project) use ($user) {
                // Check tasks in phases
                $hasPhaseTask = $project->phases->some(function($phase) use ($user) {
                    return $phase->tasks->some(function($task) use ($user) {
                        return $task->users->contains($user->id);
                    });
                });
                
                // Check standalone tasks (tasks without phases)
                $hasStandaloneTask = $project->columns->some(function($column) use ($user) {
                    return $column->tasks->where('phase_id', null)->some(function($task) use ($user) {
                        return $task->users->contains($user->id);
                    });
                });
                
                return $hasPhaseTask || $hasStandaloneTask;
            })->sortByDesc('updated_at')->values();
            
            $projects = $userHasTasks->merge($userNoTasks);
        } elseif ($sort === 'status') {
            $projects = $query->orderByRaw("
                CASE 
                    WHEN status = 'active' THEN 1 
                    WHEN status = 'planned' THEN 2 
                    WHEN status = 'paused' THEN 3 
                    WHEN status = 'completed' THEN 4 
                    WHEN status = 'archived' THEN 5 
                    ELSE 6 
                END
            ")->orderBy('name', 'asc')->get();
        } else {
            // Default fallback
            $projects = $query->orderBy('updated_at', 'desc')->get();
        }

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
            'use_default_columns' => 'boolean',
        ]);

        $project = Project::create($validated);

        // Log project creation
        \App\Models\ProjectActivity::log($project->id, auth()->id(), 'created', null);

        // Create default columns if requested
        if ($request->boolean('use_default_columns')) {
            $this->createDefaultColumnsForProject($project);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    /**
     * Create default columns for a project
     */
    protected function createDefaultColumnsForProject(Project $project)
    {
        // Only create if project has no columns
        if ($project->columns()->exists()) {
            return;
        }

        $defaultColumns = [
            ['name' => 'Backlog', 'position' => 1],
            ['name' => 'In Progress', 'position' => 2],
            ['name' => 'Ready for Testing', 'position' => 3],
            ['name' => 'Completed', 'position' => 4],
        ];

        foreach ($defaultColumns as $columnData) {
            $project->columns()->create($columnData);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        // Load phases (exclude completed by default)
        $project->load(['phases' => function ($query) {
            $query->where('status', '!=', 'completed')
                  ->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
                  ->orderBy('start_date', 'asc');
        }, 'columns']);

        // Get upcoming tasks (next 5, ordered by due date)
        $upcomingTasks = \App\Models\Task::whereHas('column', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->where('status', '!=', 'completed')
        ->whereNotNull('due_date')
        ->with(['phase', 'column'])
        ->orderByRaw('CASE WHEN due_date < ? THEN 0 ELSE 1 END', [today()])
        ->orderBy('due_date', 'asc')
        ->limit(5)
        ->get();

        // Calculate project health metrics
        $activePhasesCount = $project->phases()->where('status', '!=', 'completed')->count();
        
        $overdueTasks = \App\Models\Task::whereHas('column', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->where('status', '!=', 'completed')
        ->whereNotNull('due_date')
        ->where('due_date', '<', today())
        ->count();

        // Find nearest upcoming due date (task or phase)
        $nearestTaskDate = \App\Models\Task::whereHas('column', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->where('status', '!=', 'completed')
        ->whereNotNull('due_date')
        ->where('due_date', '>=', today())
        ->orderBy('due_date', 'asc')
        ->value('due_date');

        $nearestPhaseDate = $project->phases()
            ->where('status', '!=', 'completed')
            ->whereNotNull('end_date')
            ->where('end_date', '>=', today())
            ->orderBy('end_date', 'asc')
            ->value('end_date');

        $nearestDueDate = null;
        if ($nearestTaskDate && $nearestPhaseDate) {
            $nearestDueDate = $nearestTaskDate->isBefore($nearestPhaseDate) ? $nearestTaskDate : $nearestPhaseDate;
        } elseif ($nearestTaskDate) {
            $nearestDueDate = $nearestTaskDate;
        } elseif ($nearestPhaseDate) {
            $nearestDueDate = $nearestPhaseDate;
        }

        return view('projects.show', compact('project', 'upcomingTasks', 'activePhasesCount', 'overdueTasks', 'nearestDueDate'));
    }

    /**
     * Display the project kanban board with all tasks across phases
     */
    public function board(Request $request, Project $project)
    {
        // No longer auto-redirect to a phase - allow viewing all tasks when no phase filter is applied
        
        // Load columns ordered by position
        $columns = $project->columns()->orderBy('position')->get();
        
        // Load phases for filter dropdown
        $phases = $project->phases()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        
        // Build task query (include tasks with or without phases)
        $tasksQuery = \App\Models\Task::whereHas('column', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        });
        
        // Apply phase filter if provided
        $phaseSynopsis = null;
        if ($request->has('phase') && $request->phase) {
            $tasksQuery->where('phase_id', $request->phase);
            // Load the filtered phase with task counts for synopsis
            $phaseSynopsis = $project->phases()->find($request->phase);
            if ($phaseSynopsis) {
                $phaseSynopsis->tasks_count = $phaseSynopsis->tasks()->count();
                $phaseSynopsis->completed_tasks_count = $phaseSynopsis->tasks()->where('status', 'completed')->count();
            }
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
        
        // Load all tasks for this project (optionally filtered by phase)
        // Eager load phase and users relationships and order by position within each column
        $tasks = $tasksQuery->with(['phase', 'users'])->get()->groupBy('column_id');
        
        // Get users who have tasks assigned in this project with counts
        $usersWithTasks = \App\Models\User::whereHas('tasks', function ($query) use ($project) {
            $query->whereHas('column', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            });
        })->withCount(['tasks' => function ($query) use ($project) {
            $query->whereHas('column', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            });
        }])->orderBy('name')->get();
        
        return view('projects.board', compact('project', 'columns', 'tasks', 'phases', 'phaseSynopsis', 'usersWithTasks'));
    }

    /**
     * Create default columns for a project and redirect to board
     */
    public function createDefaultColumns(Request $request, Project $project)
    {
        // Only create if project has no columns
        if ($project->columns()->exists()) {
            return redirect()->route('projects.board', $project)
                ->with('error', 'Default columns can only be created for projects with no existing columns.');
        }

        $this->createDefaultColumnsForProject($project);

        return redirect()->route('projects.board', $project)
            ->with('success', 'Default columns created successfully.');
    }

    /**
     * Return task creation form for board modal
     */
    public function taskForm(Request $request, Project $project)
    {
        $columnId = $request->query('column');
        $isGlobalModal = $request->query('global_modal') === '1';
        
        // For global modal, pick the first column if none specified
        if ($isGlobalModal && !$columnId) {
            $columnId = $project->columns->first()?->id;
        }
        
        $phases = $project->phases()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        $users = \App\Models\User::orderBy('name')->get();
        
        return view('projects.partials.board-task-form', compact('project', 'columnId', 'phases', 'users'));
    }

    /**
     * Return task edit form for board modal
     */
    public function editTask(Project $project, \App\Models\Task $task, $activeTab = 'details')
    {
        // Verify task belongs to this project via column
        if ($task->column->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        $phases = $project->phases()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        $columns = $project->columns;
        $users = \App\Models\User::orderBy('name')->get();
        $task->load(['users', 'comments.user', 'activities.user', 'creator', 'updater']);
        
        return view('projects.partials.board-task-form', compact('project', 'task', 'phases', 'columns', 'users', 'activeTab'));
    }

    /**
     * Show task details for board modal
     */
    public function showTask(Project $project, \App\Models\Task $task)
    {
        // Verify task belongs to this project via column
        if ($task->column->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        $task->load(['phase', 'column', 'users', 'creator', 'updater']);
        $phase = $task->phase;

        return view('tasks.show', compact('project', 'phase', 'task'));
    }

    /**
     * Store a task from the board modal
     */
    public function storeTask(Request $request, Project $project)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'phase_id' => 'nullable|exists:phases,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Clients can only create tasks with status 'planned' and cannot assign users
        if ($user->isClient()) {
            $validated['status'] = 'planned';
            $validated['assignees'] = [];
        }

        // If phase_id provided, verify it belongs to this project
        if (!empty($validated['phase_id'])) {
            $phase = \App\Models\Phase::findOrFail($validated['phase_id']);
            if ($phase->project_id !== $project->id) {
                abort(403, 'Phase does not belong to this project.');
            }
        }

        // Set position to end of column
        $validated['position'] = \App\Models\Task::getNextPositionInColumn($validated['column_id']);

        // Set creator
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        // Set completed_at if status is completed
        if ($validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        // Create the task
        $task = \App\Models\Task::create($validated);

        // Sync assignees
        $oldAssignees = [];
        $newAssignees = $validated['assignees'] ?? [];
        $task->users()->sync($newAssignees);

        // Log initial activities
        if (!empty($newAssignees)) {
            \App\Models\TaskActivity::log($task->id, auth()->id(), 'assignees_changed', [
                'added' => $newAssignees,
            ]);
        }

        // Check if this is from the modal with HTMX
        $fromBoardModal = $request->input('from_board_modal');
        
        if (request()->header('HX-Request') && $fromBoardModal) {
            // HTMX: Return updated column task list
            $column = $project->columns()->find($validated['column_id']);
            $tasks = \App\Models\Task::whereHas('column', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->with(['phase', 'users'])
            ->get()
            ->groupBy('column_id');
            
            $html = view('projects.partials.board-column-tasks', [
                'column' => $column,
                'columnTasks' => $tasks->get($validated['column_id'], collect()),
                'project' => $project,
                'allColumns' => $project->columns,
                'isProjectBoard' => true
            ])->render();
            
            $html .= '<script>bootstrap.Modal.getInstance(document.getElementById("taskModal")).hide();</script>';
            
            return response($html);
        }

        // Redirect to task detail page if it has a phase, otherwise to board
        if ($task->phase_id) {
            return redirect()->route('projects.phases.tasks.show', [$project, $task->phase, $task])
                ->with('success', 'Task created successfully.');
        } else {
            return redirect()->route('projects.board', $project)
                ->with('success', 'Task created successfully.');
        }
    }

    /**
     * Store a task from the task create form (with selectable phase)
     */
    public function storeProjectTask(Request $request, Project $project)
    {
        $validated = $request->validate([
            'phase_id' => 'nullable|exists:phases,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // If phase_id provided, verify it belongs to this project
        if (!empty($validated['phase_id'])) {
            $phase = \App\Models\Phase::findOrFail($validated['phase_id']);
            if ($phase->project_id !== $project->id) {
                abort(403, 'Phase does not belong to this project.');
            }
        }

        // Set position to end of column
        $validated['position'] = \App\Models\Task::getNextPositionInColumn($validated['column_id']);

        // Set creator
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        // Set completed_at if status is completed
        if ($validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        // Create the task
        $task = \App\Models\Task::create($validated);

        // Sync assignees
        $newAssignees = $validated['assignees'] ?? [];
        $task->users()->sync($newAssignees);

        // Log initial activities
        if (!empty($newAssignees)) {
            \App\Models\TaskActivity::log($task->id, auth()->id(), 'assignees_changed', [
                'added' => $newAssignees,
            ]);
        }

        // Redirect to task detail page if it has a phase, otherwise to board
        if ($task->phase_id) {
            return redirect()->route('projects.phases.tasks.show', [$project, $task->phase, $task])
                ->with('success', 'Task created successfully.');
        } else {
            return redirect()->route('projects.board', $project)
                ->with('success', 'Task created successfully.');
        }
    }

    /**
     * Update a task from the board modal
     */
    public function updateTask(Request $request, Project $project, \App\Models\Task $task)
    {
        // Verify task belongs to this project
        if ($task->phase && $task->phase->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        $user = auth()->user();

        $validated = $request->validate([
            'phase_id' => 'nullable|exists:phases,id',
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Clients cannot change status, column, start_date, or assignees
        if ($user->isClient()) {
            $validated['status'] = $task->status;
            $validated['column_id'] = $task->column_id;
            $validated['start_date'] = $task->start_date;
            $validated['assignees'] = $task->users->pluck('id')->toArray();
        }

        // If phase_id provided, verify it belongs to this project
        if (!empty($validated['phase_id'])) {
            $phase = \App\Models\Phase::findOrFail($validated['phase_id']);
            if ($phase->project_id !== $project->id) {
                abort(403, 'Phase does not belong to this project.');
            }
        }

        $oldColumnId = $task->column_id;
        $oldStatus = $task->status;
        $oldPhaseId = $task->phase_id;
        $oldDueDate = $task->due_date?->format('Y-m-d');
        $oldAssignees = $task->users->pluck('id')->toArray();
        
        // Set updater
        $validated['updated_by'] = auth()->id();

        // Handle completed_at timestamp
        if ($validated['status'] === 'completed' && $oldStatus !== 'completed') {
            $validated['completed_at'] = now();
        } elseif ($validated['status'] !== 'completed' && $oldStatus === 'completed') {
            $validated['completed_at'] = null;
        }
        
        // Update the task
        $task->update($validated);

        // Sync assignees
        $newAssignees = $validated['assignees'] ?? [];
        $task->users()->sync($newAssignees);

        // Log meaningful changes
        $userId = auth()->id();

        // Status changes
        if ($oldStatus !== $validated['status']) {
            if ($validated['status'] === 'completed') {
                \App\Models\TaskActivity::log($task->id, $userId, 'completed', null);
            } elseif ($oldStatus === 'completed') {
                \App\Models\TaskActivity::log($task->id, $userId, 'reopened', null);
            } else {
                \App\Models\TaskActivity::log($task->id, $userId, 'status_changed', [
                    'from' => $oldStatus,
                    'to' => $validated['status'],
                ]);
            }
        }

        // Phase changes
        if ($oldPhaseId != ($validated['phase_id'] ?? null)) {
            $newPhase = $validated['phase_id'] ? \App\Models\Phase::find($validated['phase_id']) : null;
            \App\Models\TaskActivity::log($task->id, $userId, 'phase_changed', [
                'from' => $oldPhaseId,
                'to' => $validated['phase_id'],
                'to_name' => $newPhase?->name,
            ]);
        }

        // Assignee changes
        if ($oldAssignees !== $newAssignees) {
            \App\Models\TaskActivity::log($task->id, $userId, 'assignees_changed', [
                'added' => array_diff($newAssignees, $oldAssignees),
                'removed' => array_diff($oldAssignees, $newAssignees),
            ]);
        }

        // Due date changes
        $newDueDate = $validated['due_date'] ?? null;
        if ($oldDueDate !== $newDueDate) {
            \App\Models\TaskActivity::log($task->id, $userId, 'due_date_changed', [
                'from' => $oldDueDate,
                'to' => $newDueDate,
            ]);
        }

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
            $tasks = \App\Models\Task::whereHas('column', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->with(['phase', 'users'])
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
     * Move a task to a new position and/or column via drag & drop
     */
    public function moveTask(Request $request, Project $project, \App\Models\Task $task)
    {
        $validated = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'position' => 'required|integer|min:0',
        ]);

        // Verify task belongs to this project
        if ($task->phase && $task->phase->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        // Verify column belongs to this project
        $column = $project->columns()->findOrFail($validated['column_id']);

        $oldColumnId = $task->column_id;
        $oldPosition = $task->position;

        // Update task
        $task->column_id = $validated['column_id'];
        $task->position = $validated['position'];
        $task->updated_by = auth()->id();
        $task->save();

        // Log column change activity
        if ($oldColumnId !== $validated['column_id']) {
            $oldColumn = $project->columns()->find($oldColumnId);
            $newColumn = $project->columns()->find($validated['column_id']);
            \App\Models\TaskActivity::log($task->id, auth()->id(), 'column_changed', [
                'from' => $oldColumnId,
                'to' => $validated['column_id'],
                'from_name' => $oldColumn?->name,
                'to_name' => $newColumn?->name,
            ]);
        }

        // Adjust positions in the old column (if changed)
        if ($oldColumnId !== $validated['column_id']) {
            \App\Models\Task::where('column_id', $oldColumnId)
                ->where('position', '>', $oldPosition)
                ->decrement('position');
        }

        // Adjust positions in the new column to make space for the moved task
        \App\Models\Task::where('column_id', $validated['column_id'])
            ->where('id', '!=', $task->id)
            ->where('position', '>=', $validated['position'])
            ->increment('position');

        // Return updated column(s) HTML for the affected columns
        $columns = $project->columns;
        $tasks = \App\Models\Task::whereHas('column', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->with(['phase', 'users'])
        ->get()
        ->groupBy('column_id');

        // If column changed, return both old and new columns
        if ($oldColumnId !== $validated['column_id']) {
            $oldColumn = $columns->firstWhere('id', $oldColumnId);
            $newColumn = $columns->firstWhere('id', $validated['column_id']);

            $response = [
                'success' => true,
                'columnChanged' => true,
                'oldColumnHtml' => view('projects.partials.board-column-tasks', [
                    'column' => $oldColumn,
                    'columnTasks' => $tasks->get($oldColumnId, collect()),
                    'project' => $project,
                    'allColumns' => $columns,
                    'isProjectBoard' => true
                ])->render(),
                'newColumnHtml' => view('projects.partials.board-column-tasks', [
                    'column' => $newColumn,
                    'columnTasks' => $tasks->get($validated['column_id'], collect()),
                    'project' => $project,
                    'allColumns' => $columns,
                    'isProjectBoard' => true
                ])->render(),
                'oldColumnId' => $oldColumnId,
                'newColumnId' => $validated['column_id']
            ];

            return response()->json($response);
        }

        return response()->json(['success' => true, 'columnChanged' => false]);
    }

    /**
     * Store a comment on a task
     */
    public function storeComment(Request $request, Project $project, \App\Models\Task $task)
    {
        \Log::info('storeComment called', [
            'task_id' => $task->id,
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'body' => $request->input('body'),
        ]);
        
        // Verify task belongs to this project
        if ($task->column->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        $validated = $request->validate([
            'body' => 'required|string|max:1000',
        ]);

        $comment = \App\Models\TaskComment::create([
            'task_id' => $task->id,
            'user_id' => auth()->id(),
            'body' => $validated['body'],
        ]);
        
        \Log::info('Comment created', ['comment_id' => $comment->id]);

        // Load the user for display
        $comment->load('user');

        // Return the task edit form with comments tab active
        return $this->editTask($project, $task, 'discussion');
    }

    /**
     * Delete a comment from a task
     */
    public function deleteComment(Request $request, Project $project, \App\Models\Task $task, \App\Models\TaskComment $comment)
    {
        // Verify task belongs to this project
        if ($task->column->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        // Verify comment belongs to this task
        if ($comment->task_id !== $task->id) {
            abort(403, 'Comment does not belong to this task.');
        }

        // Check authorization: user owns comment or is admin
        $user = auth()->user();
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'You are not authorized to delete this comment.');
        }

        $comment->delete();

        // Return the task edit form with comments tab active
        return $this->editTask($project, $task, 'discussion');
    }

    /**
     * Delete a task from the board
     */
    public function deleteTask(Request $request, Project $project, \App\Models\Task $task)
    {
        // Clients cannot delete tasks
        if (auth()->user()->isClient()) {
            abort(403, 'Clients cannot delete tasks.');
        }

        // Verify task belongs to this project
        if ($task->column->project_id !== $project->id) {
            abort(403, 'Task does not belong to this project.');
        }

        // Check authorization: user owns task or is admin
        $user = auth()->user();
        if ($task->user_id !== $user->id && !$user->isAdmin()) {
            abort(403, 'You are not authorized to delete this task.');
        }

        $task->delete();

        // Return updated column HTML for HTMX
        $column = $project->columns()->find($task->column_id);
        $tasks = \App\Models\Task::whereHas('column', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->with(['phase', 'users'])
        ->get()
        ->groupBy('column_id');

        $html = view('projects.partials.board-column-tasks', [
            'column' => $column,
            'columnTasks' => $tasks->get($task->column_id, collect()),
            'project' => $project,
            'allColumns' => $project->columns,
            'isProjectBoard' => true
        ])->render();

        $html .= '<script>bootstrap.Modal.getInstance(document.getElementById("taskModal")).hide();</script>';

        return response($html);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        // Clients cannot access settings
        if (auth()->user()->isClient()) {
            abort(403, 'Clients cannot access project settings.');
        }
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

        $oldStatus = $project->status;
        $project->update($validated);

        // Log project update
        if ($oldStatus !== $validated['status']) {
            \App\Models\ProjectActivity::log($project->id, auth()->id(), 'status_changed', [
                'from' => $oldStatus,
                'to' => $validated['status'],
            ]);
        } else {
            \App\Models\ProjectActivity::log($project->id, auth()->id(), 'updated', null);
        }

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

        // Get users who have tasks assigned in this project with counts
        $users = User::whereHas('tasks', function ($query) use ($project) {
            $query->whereHas('column', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            });
        })->withCount(['tasks' => function ($query) use ($project) {
            $query->whereHas('column', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            });
        }])->orderBy('name')->get();

        // Load phases with their tasks, eager loading columns for task display
        // Include all tasks, we'll filter by due dates in the view
        $project->load(['phases' => function ($query) use ($showCompleted, $request) {
            if (!$showCompleted) {
                $query->where('status', '!=', 'completed');
            }
            $query->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
                  ->orderBy('start_date', 'asc')
                  ->with(['tasks' => function ($taskQuery) use ($request, $showCompleted) {
                      $taskQuery->with('column', 'users');

                      // Filter by assignee
                      if ($request->filled('assignee')) {
                          $assignee = $request->input('assignee');
                          if ($assignee === 'unassigned') {
                              $taskQuery->whereDoesntHave('users');
                          } else {
                              $taskQuery->whereHas('users', function ($query) use ($assignee) {
                                  $query->where('users.id', $assignee);
                              });
                          }
                      }

                      if (!$showCompleted) {
                          $taskQuery->where('status', '!=', 'completed');
                      }

                      $taskQuery->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
                                ->orderBy('due_date', 'asc');
                  }]);
        }]);

        // Load standalone tasks (tasks without phases) that have due dates
        $standaloneTasks = \App\Models\Task::with('column', 'users')
            ->whereHas('column', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            })
            ->whereNull('phase_id')
            ->where(function ($query) {
                // Tasks with explicit due_date
                $query->whereNotNull('due_date');
            })
            ->when($request->filled('assignee'), function ($q) use ($request) {
                $assignee = $request->input('assignee');
                if ($assignee === 'unassigned') {
                    $q->whereDoesntHave('users');
                } else {
                    $q->whereHas('users', function ($query) use ($assignee) {
                        $query->where('users.id', $assignee);
                    });
                }
            })
            ->when(!$showCompleted, function ($q) {
                $q->where('status', '!=', 'completed');
            })
            ->orderBy('due_date', 'asc')
            ->get();

        // Load standalone tasks without due dates
        $standaloneTasksNoDates = \App\Models\Task::with('column', 'users')
            ->whereHas('column', function ($q) use ($project) {
                $q->where('project_id', $project->id);
            })
            ->whereNull('phase_id')
            ->whereNull('due_date')
            ->when($request->filled('assignee'), function ($q) use ($request) {
                $assignee = $request->input('assignee');
                if ($assignee === 'unassigned') {
                    $q->whereDoesntHave('users');
                } else {
                    $q->whereHas('users', function ($query) use ($assignee) {
                        $query->where('users.id', $assignee);
                    });
                }
            })
            ->when(!$showCompleted, function ($q) {
                $q->where('status', '!=', 'completed');
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return view('projects.schedule', compact('project', 'showCompleted', 'standaloneTasks', 'standaloneTasksNoDates', 'users'));
    }

    /**
     * Display the timeline view for a project.
     */
    public function timeline(Request $request, Project $project)
    {
        // Get optional date filters from query params
        $filterStart = $request->has('start') ? \Carbon\Carbon::parse($request->input('start')) : null;
        $filterEnd = $request->has('end') ? \Carbon\Carbon::parse($request->input('end')) : null;

        $showCompleted = $request->query('show_completed', '0') === '1';

        // Get phases that have at least one date
        $phasesQuery = $project->phases()
            ->where(function ($query) {
                $query->whereNotNull('start_date')
                      ->orWhereNotNull('end_date');
            });
        
        if (!$showCompleted) {
            $phasesQuery->where('status', '!=', 'completed');
        }

        // Apply date filters if provided
        if ($filterStart || $filterEnd) {
            $phasesQuery->where(function ($query) use ($filterStart, $filterEnd) {
                if ($filterStart && $filterEnd) {
                    // Phase overlaps with filter range
                    $query->where(function ($q) use ($filterStart, $filterEnd) {
                        $q->where(function ($q2) use ($filterStart, $filterEnd) {
                            // start_date falls within range
                            $q2->whereBetween('start_date', [$filterStart, $filterEnd]);
                        })->orWhere(function ($q2) use ($filterStart, $filterEnd) {
                            // end_date falls within range
                            $q2->whereBetween('end_date', [$filterStart, $filterEnd]);
                        })->orWhere(function ($q2) use ($filterStart, $filterEnd) {
                            // phase spans entire range
                            $q2->where('start_date', '<=', $filterStart)
                               ->where('end_date', '>=', $filterEnd);
                        });
                    });
                } elseif ($filterStart) {
                    // At least one date is on or after filter start
                    $query->where(function ($q) use ($filterStart) {
                        $q->where('start_date', '>=', $filterStart)
                          ->orWhere('end_date', '>=', $filterStart);
                    });
                } elseif ($filterEnd) {
                    // At least one date is on or before filter end
                    $query->where(function ($q) use ($filterEnd) {
                        $q->where('start_date', '<=', $filterEnd)
                          ->orWhere('end_date', '<=', $filterEnd);
                    });
                }
            });
        }
        
        $phases = $phasesQuery
            ->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date', 'asc')
            ->orderBy('end_date', 'asc')
            ->get();

        // If no phases with dates, return early
        if ($phases->isEmpty()) {
            // Set default filter values for form population if not already set
            if (!$filterStart && !$filterEnd) {
                $today = \Carbon\Carbon::today();
                $filterStart = $today;
                $filterEnd = $today->copy()->addMonths(2);
            }
            
            return view('projects.timeline', [
                'project' => $project,
                'phases' => $phases,
                'weeks' => [],
                'timelineStart' => null,
                'timelineEnd' => null,
                'tooWide' => false,
                'filterStart' => $filterStart ? $filterStart->format('Y-m-d') : null,
                'filterEnd' => $filterEnd ? $filterEnd->format('Y-m-d') : null,
                'showCompleted' => $showCompleted,
            ]);
        }

        // Calculate timeline window
        if ($filterStart && $filterEnd) {
            // Use filter range
            $timelineStart = $filterStart->copy()->startOfWeek();
            $timelineEnd = $filterEnd->copy()->endOfWeek();
        } else {
            // Default to next 2 months starting today
            $today = \Carbon\Carbon::today();
            $timelineStart = $today->copy()->startOfWeek();
            $timelineEnd = $today->copy()->addMonths(2)->endOfWeek();
            
            // Set default filter values for form population
            $filterStart = $today;
            $filterEnd = $today->copy()->addMonths(2);
        }
        
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
        // Count phase end_date and task due_date occurrences per week
        // Tasks use "effective due date" - explicit due_date or phase end_date fallback
        foreach ($weeks as $index => $week) {
            $dueDateCount = 0;
            
            // Count phase end dates in this week
            foreach ($phases as $phase) {
                if ($phase->end_date && 
                    $phase->end_date->gte($week['start']) && 
                    $phase->end_date->lte($week['end'])) {
                    $dueDateCount++;
                }
            }
            
            // Count task effective due dates in this week
            // Includes tasks with explicit due_date OR tasks inheriting from phase end_date
            $tasks = \App\Models\Task::with(['phase', 'column'])
                ->whereHas('column', function ($q) use ($project) {
                    $q->where('project_id', $project->id);
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

        // Add task counts to each phase
        $phases = $phases->map(function ($phase) use ($showCompleted) {
            $taskCounts = $phase->tasks()
                ->when(!$showCompleted, function ($q) {
                    $q->where('status', '!=', 'completed');
                })
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            
            // Ensure all statuses are represented
            $taskCounts = array_merge([
                'planned' => 0,
                'active' => 0,
                'completed' => 0,
            ], $taskCounts);
            
            $phase->task_counts = $taskCounts;
            return $phase;
        });

        return view('projects.timeline', [
            'project' => $project,
            'phases' => $phases,
            'weeks' => $weeks,
            'timelineStart' => $timelineStart,
            'timelineEnd' => $timelineEnd,
            'tooWide' => $tooWide,
            'filterStart' => $filterStart ? $filterStart->format('Y-m-d') : null,
            'filterEnd' => $filterEnd ? $filterEnd->format('Y-m-d') : null,
            'showCompleted' => $showCompleted,
        ]);
    }

    /**
     * Show project sharing settings.
     * Displays who can see the project and allows managing shares.
     */
    public function sharing(Project $project)
    {
        // Clients cannot access sharing
        if (auth()->user()->isClient()) {
            abort(403, 'Clients cannot access project sharing settings.');
        }

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