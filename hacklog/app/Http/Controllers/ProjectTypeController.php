<?php

namespace App\Http\Controllers;

use App\Models\ProjectType;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectTypeController extends Controller
{
    public function index()
    {
        $this->authorizeManagement();

        $types = ProjectType::query()->withCount('projects')->ordered()->get();

        return view('project-types.index', compact('types'));
    }

    public function store(Request $request)
    {
        $this->authorizeManagement();
        $validated = $this->validateType($request);
        $key = Str::slug($validated['name'], '_');

        if ($key === '') {
            return back()->withErrors(['name' => 'Project type names must contain at least one letter or number.'])->withInput();
        }

        if ($conflictingType = ProjectType::where('key', $key)->first()) {
            return back()->withErrors([
                'name' => "That name conflicts with the existing {$conflictingType->name} project type.",
            ])->withInput();
        }

        ProjectType::create([...$validated, 'key' => $key]);

        return redirect()->route('project-types.index')->with('success', 'Project type added.');
    }

    public function update(Request $request, ProjectType $projectType)
    {
        $this->authorizeManagement();
        $projectType->update($this->validateType($request, $projectType));

        return redirect()->route('project-types.index')->with('success', 'Project type updated.');
    }

    public function destroy(ProjectType $projectType)
    {
        $this->authorizeManagement();

        if ($projectType->projects()->exists()) {
            return back()->withErrors(['type' => 'Move projects to another type before deleting this one.']);
        }

        $projectType->delete();

        return redirect()->route('project-types.index')->with('success', 'Project type deleted.');
    }

    protected function validateType(Request $request, ?ProjectType $type = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('project_types', 'name')->ignore($type?->id)],
            'position' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    protected function authorizeManagement(): void
    {
        $user = auth()->user();

        if (! $user || (! $user->isAdmin() && ! $user->isTeam())) {
            abort(403, 'You are not authorized to manage project types.');
        }
    }
}
