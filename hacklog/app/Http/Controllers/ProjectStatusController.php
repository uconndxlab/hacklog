<?php

namespace App\Http\Controllers;

use App\Models\ProjectStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectStatusController extends Controller
{
    public function index()
    {
        $this->authorizeManagement();

        $statuses = ProjectStatus::query()->withCount('projects')->ordered()->get();

        return view('project-statuses.index', compact('statuses'));
    }

    public function store(Request $request)
    {
        $this->authorizeManagement();
        $validated = $this->validateStatus($request);
        $key = Str::slug($validated['name'], '_');

        if ($key === '' || ProjectStatus::where('key', $key)->exists()) {
            return back()->withErrors(['name' => 'That project status already exists.'])->withInput();
        }

        ProjectStatus::create([
            ...$validated,
            'key' => $key,
            'show_in_active_views' => $request->boolean('show_in_active_views'),
        ]);

        return redirect()->route('project-statuses.index')->with('success', 'Project status added.');
    }

    public function update(Request $request, ProjectStatus $projectStatus)
    {
        $this->authorizeManagement();
        $validated = $this->validateStatus($request, $projectStatus);
        $validated['show_in_active_views'] = $request->boolean('show_in_active_views');

        if (! $validated['show_in_active_views'] && $projectStatus->show_in_active_views && ! $this->hasAnotherActiveViewStatus($projectStatus)) {
            return back()->withErrors(['show_in_active_views' => 'At least one project status must remain enabled for active views.']);
        }

        $projectStatus->update($validated);

        return redirect()->route('project-statuses.index')->with('success', 'Project status updated.');
    }

    public function destroy(ProjectStatus $projectStatus)
    {
        $this->authorizeManagement();

        if ($projectStatus->is_system) {
            return back()->withErrors(['status' => 'Core project statuses cannot be deleted.']);
        }

        if ($projectStatus->projects()->exists()) {
            return back()->withErrors(['status' => 'Move projects to another status before deleting this one.']);
        }

        if ($projectStatus->show_in_active_views && ! $this->hasAnotherActiveViewStatus($projectStatus)) {
            return back()->withErrors(['status' => 'At least one project status must remain enabled for active views.']);
        }

        $projectStatus->delete();

        return redirect()->route('project-statuses.index')->with('success', 'Project status deleted.');
    }

    protected function validateStatus(Request $request, ?ProjectStatus $status = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('project_statuses', 'name')->ignore($status?->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'position' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    protected function authorizeManagement(): void
    {
        $user = auth()->user();

        if (! $user || (! $user->isAdmin() && ! $user->isTeam())) {
            abort(403, 'You are not authorized to manage project statuses.');
        }
    }

    protected function hasAnotherActiveViewStatus(ProjectStatus $projectStatus): bool
    {
        return ProjectStatus::query()
            ->whereKeyNot($projectStatus->getKey())
            ->where('show_in_active_views', true)
            ->exists();
    }
}
