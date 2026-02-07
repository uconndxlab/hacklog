<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project, Epic $epic)
    {
        // Tasks are shown on the Board page, so redirect there with epic filter
        return redirect()->route('projects.board', ['project' => $project, 'epic' => $epic->id]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project, Epic $epic)
    {
        $columns = $project->columns;
        $epics = $project->epics()
            ->orderByRaw('CASE WHEN status = "active" THEN 1 WHEN status = "planned" THEN 2 ELSE 3 END')
            ->orderBy('name')
            ->get();
        $users = \App\Models\User::orderBy('name')->get();
        return view('tasks.create', compact('project', 'epic', 'columns', 'epics', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project, Epic $epic)
    {
        $validated = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'due_date' => 'nullable|date|after_or_equal:start_date',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        // Set position to end of column
        $validated['position'] = Task::getNextPositionInColumn($validated['column_id']);

        $task = $epic->tasks()->create($validated);

        // Sync assignees
        if (isset($validated['assignees'])) {
            $task->users()->sync($validated['assignees']);
        }

        return redirect()->route('projects.board', ['project' => $project, 'epic' => $epic->id])
            ->with('success', 'Task created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, Epic $epic, Task $task)
    {
        $task->load('users');
        return view('tasks.show', compact('project', 'epic', 'task'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Epic $epic, Task $task)
    {
        $columns = $project->columns;
        $users = \App\Models\User::orderBy('name')->get();
        $task->load('users');
        return view('tasks.edit', compact('project', 'epic', 'task', 'columns', 'users'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, Epic $epic, Task $task)
    {
        $validated = $request->validate([
            'column_id' => 'required|exists:columns,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'assignees' => 'nullable|array',
            'assignees.*' => 'exists:users,id',
        ]);

        $oldColumnId = $task->column_id;
        
        // If column changed, set position to end of new column
        if ($oldColumnId != $validated['column_id']) {
            $validated['position'] = Task::getNextPositionInColumn($validated['column_id']);
        }
        
        $task->update($validated);

        // Sync assignees
        $task->users()->sync($validated['assignees'] ?? []);

        // Check if this is from the board view
        $fromBoard = $request->input('from_board');
        
        // HTMX request
        if (request()->header('HX-Request')) {
            if ($fromBoard && $oldColumnId != $task->column_id) {
                // Board/Epic board context: return updated source column with out-of-band swap for destination
                return $this->handleBoardColumnChange($project, $epic, $oldColumnId, $task->column_id, $fromBoard === '1');
            } elseif ($oldColumnId != $task->column_id) {
                // Legacy epic kanban view: return entire kanban board (for old kanban-board partial if still used)
                $columns = $project->columns()->with('tasks')->get();
                return view('tasks.partials.kanban-board', compact('project', 'epic', 'columns'));
            }
        }

        // Regular form submission
        $redirectRoute = $fromBoard === '1' ? 'projects.board' : 'projects.board';
        $redirectParams = $fromBoard === '1' ? [$project] : ['project' => $project, 'epic' => $epic->id];
        
        return redirect()->route($redirectRoute, $redirectParams)
            ->with('success', 'Task updated successfully.');
    }

    /**
     * Handle board column change with out-of-band swaps
     * Works for both project board (all tasks) and epic board (epic tasks only)
     */
    protected function handleBoardColumnChange(Project $project, Epic $epic, int $oldColumnId, int $newColumnId, bool $isProjectBoard = true)
    {
        $columns = $project->columns;
        
        if ($isProjectBoard) {
            // Load all tasks for this project across all epics
            $tasks = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
                $query->where('project_id', $project->id);
            })
            ->with('epic')
            ->get()
            ->groupBy('column_id');
        } else {
            // Load only tasks for this specific epic
            $tasks = $epic->tasks()->with('epic')->get()->groupBy('column_id');
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
    public function destroy(Project $project, Epic $epic, Task $task)
    {
        $task->delete();

        // HTMX request - return empty to remove the task card
        if (request()->header('HX-Request')) {
            return response('', 200);
        }

        return redirect()->route('projects.board', ['project' => $project, 'epic' => $epic->id])
            ->with('success', 'Task deleted successfully.');
    }

    /**
     * Move task up in its column (swap with previous task)
     */
    public function moveUp(Request $request, Project $project, Epic $epic, Task $task)
    {
        $task->moveUp();

        $fromBoard = $request->input('from_board');

        // HTMX request - return the updated column's task list
        if (request()->header('HX-Request')) {
            if ($fromBoard === '1') {
                // Board context: return board-column-tasks partial
                return $this->returnBoardColumnTasks($project, $task->column_id);
            } else {
                // Epic context: return epic column-tasks partial
                $column = $task->column;
                $columnTasks = $epic->tasks()->where('column_id', $column->id)->get();
                return view('tasks.partials.column-tasks', compact('project', 'epic', 'column', 'columnTasks'));
            }
        }

        // Regular request - redirect based on context
        $redirectRoute = $fromBoard === '1' ? 'projects.board' : 'projects.board';
        $redirectParams = $fromBoard === '1' ? [$project] : ['project' => $project, 'epic' => $epic->id];
        
        return redirect()->route($redirectRoute, $redirectParams)
            ->with('success', 'Task moved up.');
    }

    /**
     * Move task down in its column (swap with next task)
     */
    public function moveDown(Request $request, Project $project, Epic $epic, Task $task)
    {
        $task->moveDown();

        $fromBoard = $request->input('from_board');

        // HTMX request - return the updated column's task list
        if (request()->header('HX-Request')) {
            if ($fromBoard === '1') {
                // Board context: return board-column-tasks partial
                return $this->returnBoardColumnTasks($project, $task->column_id);
            } else {
                // Epic context: return epic column-tasks partial
                $column = $task->column;
                $columnTasks = $epic->tasks()->where('column_id', $column->id)->get();
                return view('tasks.partials.column-tasks', compact('project', 'epic', 'column', 'columnTasks'));
            }
        }

        // Regular request - redirect based on context
        $redirectRoute = $fromBoard === '1' ? 'projects.board' : 'projects.board';
        $redirectParams = $fromBoard === '1' ? [$project] : ['project' => $project, 'epic' => $epic->id];
        
        return redirect()->route($redirectRoute, $redirectParams)
            ->with('success', 'Task moved down.');
    }

    /**
     * Admin view for managing tasks in an epic
     */
    public function adminIndex(Project $project, Epic $epic)
    {
        $tasks = $epic->tasks()->with(['users', 'column'])->get();
        
        return view('admin.epics.tasks.index', compact('project', 'epic', 'tasks'));
    }

    /**
     * Bulk delete tasks (admin only)
     */
    public function bulkDelete(Request $request, Project $project, Epic $epic)
    {
        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'exists:tasks,id',
        ]);

        // Ensure all selected tasks belong to this epic
        $tasksToDelete = Task::whereIn('id', $validated['task_ids'])
            ->where('epic_id', $epic->id)
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
    protected function returnBoardColumnTasks(Project $project, int $columnId)
    {
        $columns = $project->columns;
        
        // Load all tasks for this project
        $tasks = \App\Models\Task::whereHas('epic', function ($query) use ($project) {
            $query->where('project_id', $project->id);
        })
        ->with('epic')
        ->get()
        ->groupBy('column_id');
        
        $column = $columns->firstWhere('id', $columnId);
        
        return view('projects.partials.board-column-tasks', [
            'column' => $column,
            'columnTasks' => $tasks->get($columnId, collect()),
            'project' => $project,
            'allColumns' => $columns,
            'isProjectBoard' => true
        ]);
    }
}
