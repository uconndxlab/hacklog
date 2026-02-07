<?php

namespace App\Http\Controllers;

use App\Models\Epic;
use App\Models\Project;
use Illuminate\Http\Request;

class EpicController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $epics = $project->epics()->orderBy('created_at', 'desc')->get();
        return view('epics.index', compact('project', 'epics'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        return view('epics.create', compact('project'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $epic = $project->epics()->create($validated);

        return redirect()->route('projects.board', ['project' => $project, 'epic' => $epic->id])
            ->with('success', 'Epic created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, Epic $epic)
    {
        // Load columns ordered by position
        $columns = $project->columns()->orderBy('position')->get();
        
        // Load tasks for this epic, grouped by column_id
        $tasks = $epic->tasks()->with('epic')->get()->groupBy('column_id');
        
        return view('epics.show', compact('project', 'epic', 'columns', 'tasks'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Epic $epic)
    {
        return view('epics.edit', compact('project', 'epic'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, Epic $epic)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $epic->update($validated);

        return redirect()->route('projects.board', ['project' => $project, 'epic' => $epic->id])
            ->with('success', 'Epic updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Epic $epic)
    {
        $epic->delete();

        return redirect()->route('projects.epics.index', $project)
            ->with('success', 'Epic deleted successfully.');
    }
}
