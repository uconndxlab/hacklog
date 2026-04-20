<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

class ProjectFavoriteController extends Controller
{
    public function toggle(Request $request, Project $project)
    {
        $user = $request->user();

        if ($user->favoriteProjects()->where('project_id', $project->id)->exists()) {
            $user->favoriteProjects()->detach($project->id);
        } else {
            $user->favoriteProjects()->attach($project->id);
        }

        // Create a new request with all the query parameters preserved
        // This ensures filters are maintained when toggling favorites
        $indexRequest = Request::create(
            route('projects.index'),
            'GET',
            $request->only(['search', 'scope', 'status', 'time', 'owner', 'sort'])
        );
        $indexRequest->setUserResolver($request->getUserResolver());
        
        // Return updated projects list partial (HTMX will swap)
        return app(ProjectController::class)->index($indexRequest);
    }
}
