<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Project;
use Illuminate\Http\Request;

class ColumnController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $columns = $project->columns;
        return view('columns.index', compact('project', 'columns'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        // For HTMX inline creation, return a partial
        if (request()->header('HX-Request')) {
            return view('columns.partials.create-form', compact('project'));
        }
        
        return view('columns.create', compact('project'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|integer|min:0',
            'is_default' => 'boolean',
        ]);

        // Check if position already exists
        $existingColumn = $project->columns()->where('position', $validated['position'])->first();
        if ($existingColumn) {
            return back()->withErrors(['position' => 'This position is already taken.'])->withInput();
        }

        $column = $project->columns()->create($validated);

        // HTMX request - return updated list
        if (request()->header('HX-Request')) {
            return view('columns.partials.column-row', compact('project', 'column'));
        }

        return redirect()->route('projects.columns.index', $project)
            ->with('success', 'Column created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, Column $column)
    {
        // Not needed for column management
        abort(404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Column $column)
    {
        // For HTMX inline editing, return a partial
        if (request()->header('HX-Request')) {
            return view('columns.partials.edit-form', compact('project', 'column'));
        }

        return view('columns.edit', compact('project', 'column'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, Column $column)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|integer|min:0',
            'is_default' => 'boolean',
        ]);

        // Check if position already exists (excluding current column)
        $existingColumn = $project->columns()
            ->where('position', $validated['position'])
            ->where('id', '!=', $column->id)
            ->first();
            
        if ($existingColumn) {
            return back()->withErrors(['position' => 'This position is already taken.'])->withInput();
        }

        $column->update($validated);

        // HTMX request - return updated row
        if (request()->header('HX-Request')) {
            return view('columns.partials.column-row', compact('project', 'column'));
        }

        return redirect()->route('projects.columns.index', $project)
            ->with('success', 'Column updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Column $column)
    {
        // Check if column has tasks
        $taskCount = $column->tasks()->count();
        
        if ($taskCount > 0) {
            // Find another column to move tasks to (prefer default column, otherwise first available)
            $targetColumn = $project->columns()
                ->where('id', '!=', $column->id)
                ->where('is_default', true)
                ->first();
                
            if (!$targetColumn) {
                $targetColumn = $project->columns()
                    ->where('id', '!=', $column->id)
                    ->orderBy('position')
                    ->first();
            }
            
            if (!$targetColumn) {
                // No other column exists - can't delete the last column
                if (request()->header('HX-Request')) {
                    return response()->json([
                        'error' => "Cannot delete the only column in the project. Create another column first."
                    ], 422);
                }
                
                return redirect()->route('projects.columns.index', $project)
                    ->with('error', 'Cannot delete the only column in the project. Create another column first.');
            }
            
            // Move all tasks to target column
            $column->tasks()->update(['column_id' => $targetColumn->id]);
        }
        
        $column->delete();

        // HTMX request - return empty response (row will be removed)
        if (request()->header('HX-Request')) {
            return response('', 200);
        }

        $successMessage = $taskCount > 0 
            ? "Column deleted successfully. {$taskCount} task(s) moved to '{$targetColumn->name}'."
            : 'Column deleted successfully.';
            
        return redirect()->route('projects.columns.index', $project)
            ->with('success', $successMessage);
    }
}
