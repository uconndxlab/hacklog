<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectResource;
use Illuminate\Http\Request;

class ProjectResourceController extends Controller
{
    /**
     * Display a listing of the project's resources.
     */
    public function index(Project $project)
    {
        $resources = $project->resources;
        return view('projects.resources.index', compact('project', 'resources'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Project $project)
    {
        return view('projects.resources.create', compact('project'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:link,note',
            'url' => 'nullable|string|max:2048|required_if:type,link',
            'content' => 'nullable|string|required_if:type,note',
        ]);

        $validated['project_id'] = $project->id;
        $validated['position'] = ProjectResource::getNextPositionInProject($project->id);

        ProjectResource::create($validated);

        return redirect()->route('projects.resources.index', $project)
            ->with('success', 'Resource added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project, ProjectResource $resource)
    {
        return view('projects.resources.show', compact('project', 'resource'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project, ProjectResource $resource)
    {
        return view('projects.resources.edit', compact('project', 'resource'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Project $project, ProjectResource $resource)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:link,note',
            'url' => 'nullable|string|max:2048|required_if:type,link',
            'content' => 'nullable|string|required_if:type,note',
        ]);

        $resource->update($validated);

        return redirect()->route('projects.resources.index', $project)
            ->with('success', 'Resource updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project, ProjectResource $resource)
    {
        $resource->delete();

        return redirect()->route('projects.resources.index', $project)
            ->with('success', 'Resource deleted successfully.');
    }

    /**
     * Move resource up in the list (swap with previous resource)
     */
    public function moveUp(Project $project, ProjectResource $resource)
    {
        $resource->moveUp();

        return redirect()->route('projects.resources.index', $project)
            ->with('success', 'Resource moved up.');
    }

    /**
     * Move resource down in the list (swap with next resource)
     */
    public function moveDown(Project $project, ProjectResource $resource)
    {
        $resource->moveDown();

        return redirect()->route('projects.resources.index', $project)
            ->with('success', 'Resource moved down.');
    }
}
