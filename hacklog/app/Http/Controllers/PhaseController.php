<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use App\Models\Project;
use Illuminate\Http\Request;

class PhaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Project $project)
    {
        $phases = $project->phases()->orderBy('created_at', 'desc')->get();
        return view('phases.index', compact('project', 'phases'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        return view('phases.create', compact('project'));
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

        $phase = $project->phases()->create($validated);

        return redirect()->route('projects.board', ['project' => $project, 'phase' => $phase->id])
            ->with('success', 'Phase created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, Phase $phase)
    {
        // Load columns ordered by position
        $columns = $project->columns()->orderBy('position')->get();
        
        // Load tasks for this phase, grouped by column_id
        $tasks = $phase->tasks()->with('phase')->get()->groupBy('column_id');
        
        return view('phases.show', compact('project', 'phase', 'columns', 'tasks'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, Phase $phase)
    {
        return view('phases.edit', compact('project', 'phase'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, Phase $phase)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:planned,active,completed',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $phase->update($validated);

        return redirect()->route('projects.board', ['project' => $project, 'phase' => $phase->id])
            ->with('success', 'Phase updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, Phase $phase)
    {
        $phase->delete();

        return redirect()->route('projects.phases.index', $project)
            ->with('success', 'Phase deleted successfully.');
    }
}
