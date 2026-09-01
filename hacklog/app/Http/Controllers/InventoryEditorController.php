<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectInventoryEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryEditorController extends Controller
{
    public function __construct(protected ProjectInventoryEditor $editor) {}

    public function index(): View
    {
        $projects = Project::query()
            ->with(['department', 'nestedDepartment', 'majorOffice'])
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project) => $this->editor->toRow($project))
            ->values();

        return view('reports.editor', [
            'rows' => $projects,
            'options' => $this->editor->lookupOptions(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $project = $this->editor->createDraft($request->user()?->id);

        return response()->json([
            'project' => $this->editor->toRow($project),
        ], 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', 'in:'.implode(',', ProjectInventoryEditor::FIELDS)],
            'value' => 'nullable',
        ]);

        $project = $this->editor->apply(
            $project,
            $validated['field'],
            $validated['value'] ?? null,
            $request->user()?->id
        );

        return response()->json([
            'project' => $this->editor->toRow($project),
        ]);
    }
}
