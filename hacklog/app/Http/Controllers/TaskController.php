<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project, Phase $phase)
    {
        // Tasks are shown on the Board page, so redirect there with phase filter
        return redirect()->route('projects.board', ['project' => $project, 'phase' => $phase->id]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project, Phase $phase)
    {
        $columns = $project->columns;
        $phases = $project->phases()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        
        // Get users sorted: current user first, then recent assignees on this project, then alphabetically
        $currentUser = auth()->user();
        $users = $this->getSortedUsersForProject($project, $currentUser);
        
        return view('tasks.create', compact('project', 'phase', 'columns', 'phases', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project, Phase $phase)
    {
        $validated = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(Task::STATUSES)],
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Set position to end of column
        $validated['position'] = Task::getNextPositionInColumn($validated['column_id']);

        // Set creator
        $validated['created_by'] = auth()->id();
        $validated['updated_by'] = auth()->id();

        // Set completed_at if status is completed
        if ($validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        $task = $phase->tasks()->create($validated);

        // Sync assignees
        $newAssignees = $validated['assignees'] ?? [];
        $task->users()->sync($newAssignees);

        // Log initial activities
        if (!empty($newAssignees)) {
            \App\Models\TaskActivity::log($task->id, auth()->id(), 'assignees_changed', [
                'added' => $newAssignees,
            ]);
        }

        return redirect()->route('projects.board', ['project' => $project, 'phase' => $phase->id])
            ->with('success', 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, Phase $phase, Task $task)
    {
        $task->load('users');
        return view('tasks.show', compact('project', 'phase', 'task'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Phase $phase, Task $task)
    {
        $columns = $project->columns;
        
        // Get users sorted: current user first, then recent assignees on this project, then alphabetically
        $currentUser = auth()->user();
        $users = $this->getSortedUsersForProject($project, $currentUser);
        
        $task->load('users');
        return view('tasks.edit', compact('project', 'phase', 'task', 'columns', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, Phase $phase, Task $task)
    {
        $validated = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(Task::STATUSES)],
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        $oldColumnId = $task->column_id;
        $oldStatus = $task->status;
        $oldAssignees = $task->users->pluck('id')->toArray();
        
        // Set updater
        $validated['updated_by'] = auth()->id();

        // Handle completed_at timestamp
        if ($validated['status'] === 'completed' && $oldStatus !== 'completed') {
            $validated['completed_at'] = now();
        } elseif ($validated['status'] !== 'completed' && $oldStatus === 'completed') {
            $validated['completed_at'] = null;
        }
        
        // If column changed, set position to end of new column
        if ($oldColumnId != $validated['column_id']) {
            $validated['position'] = Task::getNextPositionInColumn($validated['column_id']);
        }
        
        $task->update($validated);

        // Only sync assignees if not a simple column change from board
        $newAssignees = $validated['assignees'] ?? [];
        if (!$request->input('column_change_only')) {
            $task->users()->sync($newAssignees);
        }

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

        // Assignee changes (only if assignees were actually synced)
        if (!$request->input('column_change_only') && $oldAssignees !== $newAssignees) {
            \App\Models\TaskActivity::log($task->id, $userId, 'assignees_changed', [
                'added' => array_diff($newAssignees, $oldAssignees),
                'removed' => array_diff($oldAssignees, $newAssignees),
            ]);
        }

        // Check if this is from the board view
        $fromBoard = $request->input('from_board');
        
        // HTMX request
        if (request()->header('HX-Request')) {
            if ($fromBoard && $oldColumnId != $task->column_id) {
                // Board/Phase board context: return updated source column with out-of-band swap for destination
                return $this->handleBoardColumnChange($project, $phase, $oldColumnId, $task->column_id, $fromBoard === '1');
            } elseif ($oldColumnId != $task->column_id) {
                // Legacy phase kanban view: return entire kanban board (for old kanban-board partial if still used)
                $columns = $project->columns()->with('tasks')->get();
                return view('tasks.partials.kanban-board', compact('project', 'phase', 'columns'));
            }
        }

        // Regular form submission
        $redirectRoute = $fromBoard === '1' ? 'projects.board' : 'projects.board';
        
        // Build redirect parameters
        if ($fromBoard === '1') {
            // Project board: preserve phase and assigned filters
            $redirectParams = ['project' => $project];
            
            // Preserve phase filter
            if ($request->has('filter_phase_id') || $request->query('phase')) {
                $redirectParams['phase'] = $request->input('filter_phase_id') ?? $request->query('phase');
            }
            
            // Preserve assigned filter
            if ($request->has('filter_assigned') || $request->query('assigned')) {
                $redirectParams['assigned'] = $request->input('filter_assigned') ?? $request->query('assigned');
            }
        } else {
            // Phase-specific board
            $redirectParams = ['project' => $project, 'phase' => $phase->id];
            
            // Preserve assigned filter if present
            if ($request->has('filter_assigned') || $request->query('assigned')) {
                $redirectParams['assigned'] = $request->input('filter_assigned') ?? $request->query('assigned');
            }
        }
        
        return redirect()->route($redirectRoute, $redirectParams)
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Handle board column change with out-of-band swaps
     * Works for both project board (all tasks) and phase board (phase tasks only)
     */
    protected function handleBoardColumnChange(Project $project, Phase $phase, int $oldColumnId, int $newColumnId, bool $isProjectBoard = true)
    {
        $columns = $project->columns;
        
        if ($isProjectBoard) {
            // Build task query with same filtering logic as board view
            $tasksQuery = \App\Models\Task::whereHas('phase', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            });

            // Apply phase filter if provided in request
            if (request('phase')) {
                $tasksQuery->where('phase_id', request('phase'));
            }

            // Apply assignment filter if provided in request
            $assigned = request('assigned');
            if ($assigned === 'me') {
                // Filter to tasks assigned to current user
                $tasksQuery->whereHas('users', function ($query) {
                    $query->where('users.id', auth()->id());
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
            
            $tasks = $tasksQuery->with(['phase', 'users'])->get()->groupBy('column_id');
        } else {
            // Load only tasks for this specific phase
            $tasks = $phase->tasks()->with(['phase', 'users'])->get()->groupBy('column_id');
        }
        
        // Get the source and destination columns
        $sourceColumn = $columns->firstWhere('id', $oldColumnId);
        $destColumn = $columns->firstWhere('id', $newColumnId);
        
        // Render both column task lists
        $sourceHtml = view('projects.partials.board-column-tasks', [
            'column' => $sourceColumn,
            'columnTasks' => $tasks->get($oldColumnId, collect()),
            'project' => $project,
            'allColumns' => $columns,
            'isProjectBoard' => $isProjectBoard
        ])->render();
        
        $destHtml = view('projects.partials.board-column-tasks', [
            'column' => $destColumn,
            'columnTasks' => $tasks->get($newColumnId, collect()),
            'project' => $project,
            'allColumns' => $columns,
            'isProjectBoard' => $isProjectBoard,
            'isOob' => true
        ])->render();
        
        return response($sourceHtml . $destHtml);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Phase $phase, Task $task)
    {
        $task->delete();

        // HTMX request - return empty to remove the task card
        if (request()->header('HX-Request')) {
            return response('', 200);
        }

        return redirect()->route('projects.board', ['project' => $project, 'phase' => $phase->id])
            ->with('success', 'Task deleted successfully.');
    }

    /**
     * Move task up in its column (swap with previous task)
     */
    public function moveUp(Request $request, Project $project, Phase $phase, Task $task)
    {
        $task->moveUp();

        $fromBoard = $request->input('from_board');

        // HTMX request - return the updated column's task list
        if (request()->header('HX-Request')) {
            if ($fromBoard === '1') {
                // Board context: return board-column-tasks partial
                return $this->returnBoardColumnTasks(
                    $project, 
                    $task->column_id, 
                    $request->input('filter_phase_id'),
                    $request->input('filter_assigned')
                );
            } else {
                // Phase context: return phase column-tasks partial
                $column = $task->column;
                $columnTasks = $phase->tasks()->where('column_id', $column->id)->get();
                return view('tasks.partials.column-tasks', compact('project', 'phase', 'column', 'columnTasks'));
            }
        }

        // Regular request - redirect based on context
        $redirectRoute = $fromBoard === '1' ? 'projects.board' : 'projects.board';
        $redirectParams = $fromBoard === '1' ? [$project] : ['project' => $project, 'phase' => $phase->id];
        
        return redirect()->route($redirectRoute, $redirectParams)
            ->with('success', 'Task moved up.');
    }

    /**
     * Move task down in its column (swap with next task)
     */
    public function moveDown(Request $request, Project $project, Phase $phase, Task $task)
    {
        $task->moveDown();

        $fromBoard = $request->input('from_board');

        // HTMX request - return the updated column's task list
        if (request()->header('HX-Request')) {
            if ($fromBoard === '1') {
                // Board context: return board-column-tasks partial
                return $this->returnBoardColumnTasks(
                    $project, 
                    $task->column_id, 
                    $request->input('filter_phase_id'),
                    $request->input('filter_assigned')
                );
            } else {
                // Phase context: return phase column-tasks partial
                $column = $task->column;
                $columnTasks = $phase->tasks()->where('column_id', $column->id)->get();
                return view('tasks.partials.column-tasks', compact('project', 'phase', 'column', 'columnTasks'));
            }
        }

        // Regular request - redirect based on context
        $redirectRoute = $fromBoard === '1' ? 'projects.board' : 'projects.board';
        $redirectParams = $fromBoard === '1' ? [$project] : ['project' => $project, 'phase' => $phase->id];
        
        return redirect()->route($redirectRoute, $redirectParams)
            ->with('success', 'Task moved down.');
    }

    /**
     * Admin view for managing tasks in a phase
     */
    public function adminIndex(Project $project, Phase $phase)
    {
        $tasks = $phase->tasks()->with(['users', 'column'])->get();
        
        return view('admin.phases.tasks.index', compact('project', 'phase', 'tasks'));
    }

    /**
     * Bulk delete tasks (admin only)
     */
    public function bulkDelete(Request $request, Project $project, Phase $phase)
    {
        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        // Ensure all selected tasks belong to this phase
        $tasksToDelete = Task::whereIn('id', $validated['task_ids'])
            ->where('phase_id', $phase->id)
            ->get();

        if ($tasksToDelete->isEmpty()) {
            return back()->with('error', 'No valid tasks selected for deletion.');
        }

        $deletedCount = $tasksToDelete->count();
        
        // Delete the tasks (this will also handle relationships due to cascade)
        Task::whereIn('id', $tasksToDelete->pluck('id'))->delete();

        return back()->with('success', "Successfully deleted {$deletedCount} task(s).");
    }

    /**
     * Helper method to return board column tasks for HTMX
     */
    protected function returnBoardColumnTasks(Project $project, int $columnId, $filterPhaseId = null, $filterAssigned = null)
    {
        $columns = $project->columns;
        $column = $columns->firstWhere('id', $columnId);
        
        // Build query with optional filters
        $query = \App\Models\Task::where('column_id', $columnId);
        
        // Apply phase filter if provided
        if ($filterPhaseId) {
            $query->where('phase_id', $filterPhaseId);
        }
        
        // Apply assignment filter if provided
        if ($filterAssigned === 'me') {
            $query->whereHas('users', function ($q) {
                $q->where('users.id', auth()->id());
            });
        } elseif ($filterAssigned === 'none') {
            $query->whereDoesntHave('users');
        } elseif ($filterAssigned && is_numeric($filterAssigned)) {
            $query->whereHas('users', function ($q) use ($filterAssigned) {
                $q->where('users.id', $filterAssigned);
            });
        }
        
        $columnTasks = $query->with(['phase', 'users', 'creator'])->get();
        
        return view('projects.partials.board-column-tasks', [
            'column' => $column,
            'columnTasks' => $columnTasks,
            'project' => $project,
            'allColumns' => $columns,
            'isProjectBoard' => true,
            'filterPhaseId' => $filterPhaseId
        ]);
    }

    /**
     * Get users sorted for assignment: current user first, then recent assignees on this project, then alphabetically
     */
    private function getSortedUsersForProject($project, $currentUser)
    {
        // Get all users assigned to tasks in this project, ordered by most recent assignment
        $recentAssigneeIds = \DB::table('task_user')
            ->join('tasks', 'task_user.task_id', '=', 'tasks.id')
            ->join('columns', 'tasks.column_id', '=', 'columns.id')
            ->where('columns.project_id', $project->id)
            ->select('task_user.user_id', \DB::raw('MAX(task_user.created_at) as last_assigned_at'))
            ->groupBy('task_user.user_id')
            ->orderBy('last_assigned_at', 'desc')
            ->pluck('user_id')
            ->toArray();

        // Get all users
        $allUsers = \App\Models\User::all();

        // Separate current user, recent assignees, and others
        $sortedUsers = collect();

        // 1. Current user first (if exists)
        if ($currentUser) {
            $sortedUsers->push($allUsers->find($currentUser->id));
        }

        // 2. Recent assignees (excluding current user if already added)
        $recentAssignees = $allUsers->whereIn('id', $recentAssigneeIds)
            ->sortBy(function ($user) use ($recentAssigneeIds) {
                return array_search($user->id, $recentAssigneeIds);
            });

        foreach ($recentAssignees as $user) {
            if (!$sortedUsers->contains('id', $user->id)) {
                $sortedUsers->push($user);
            }
        }

        // 3. All other users sorted alphabetically
        $remainingUsers = $allUsers->whereNotIn('id', $sortedUsers->pluck('id'))
            ->sortBy('name');

        foreach ($remainingUsers as $user) {
            $sortedUsers->push($user);
        }

        return $sortedUsers->filter(); // Remove nulls (in case current user doesn't exist)
    }
}
