<?php

namespace App\Services;

use App\Http\Controllers\ReportController;
use App\Models\Department;
use App\Models\MajorOffice;
use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProjectInventoryEditor
{
    public const FIELDS = [
        'status',
        'name',
        'project_type',
        'launch_date',
        'department_id',
        'nested_department_id',
        'major_office_id',
        'client_pi',
        'client_category',
        'uconn_affiliation',
        'grant_value',
        'sponsor',
    ];

    public function lookupOptions(): array
    {
        $homeDepartments = Department::home()->orderBy('name')->get();
        $nestedByHome = Department::nested()
            ->orderBy('name')
            ->get()
            ->groupBy('parent_id')
            ->map(fn ($group) => $group->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
            ])->values())
            ->all();

        return [
            'statuses' => collect(ReportController::STATUS_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'types' => collect(Project::TYPE_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'departments' => $homeDepartments->map(fn (Department $department) => [
                'id' => $department->id,
                'name' => $department->name,
            ])->values()->all(),
            'nestedByHome' => $nestedByHome,
            'offices' => MajorOffice::orderBy('name')->get()->map(fn (MajorOffice $office) => [
                'id' => $office->id,
                'name' => $office->name,
            ])->values()->all(),
            'categories' => collect(Project::CLIENT_CATEGORY_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'affiliations' => collect(Project::AFFILIATION_LABELS)
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
        ];
    }

    public function toRow(Project $project): array
    {
        return [
            'id' => $project->id,
            'status' => $project->status,
            'name' => $project->name,
            'project_url' => route('projects.show', $project),
            'project_type' => $project->project_type,
            'launch_date' => $project->launch_date?->toDateString(),
            'department_id' => $project->department_id,
            'nested_department_id' => $project->nested_department_id,
            'major_office_id' => $project->major_office_id,
            'client_pi' => $project->client_pi,
            'client_category' => $project->client_category,
            'uconn_affiliation' => $project->uconn_affiliation,
            'grant_value' => $project->grant_value !== null ? (float) $project->grant_value : null,
            'sponsor' => $project->sponsor,
        ];
    }

    public function apply(Project $project, string $field, mixed $value, ?int $userId): Project
    {
        if (! in_array($field, self::FIELDS, true)) {
            throw ValidationException::withMessages([
                'field' => 'That column is not editable.',
            ]);
        }

        $normalized = $this->normalizeIncoming($field, $value);
        $payload = [$field => $normalized];

        if ($field === 'department_id') {
            $payload['nested_department_id'] = $this->nestedBelongsToHome(
                $project->nested_department_id,
                $normalized
            ) ? $project->nested_department_id : null;
        }

        if ($field === 'nested_department_id') {
            $payload['department_id'] = $project->department_id;
        }

        $rules = array_intersect_key($this->rules(), $payload);
        $validated = Validator::make($payload, $rules)->validate();
        $validated = $this->assertDepartmentRelationship($validated);

        $changed = array_intersect_key($validated, array_flip(self::FIELDS));
        $project->fill($changed);
        $project->save();

        ProjectActivity::log($project->id, $userId, 'updated', [
            'source' => 'inventory_editor',
            'field' => $field,
        ]);

        return $project->fresh(['department', 'nestedDepartment', 'majorOffice']);
    }

    public function createDraft(?int $userId): Project
    {
        $project = Project::create([
            'name' => 'Untitled project',
            'status' => Project::STATUS_PLANNING,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        ProjectActivity::log($project->id, $userId, 'created', [
            'source' => 'inventory_editor',
        ]);

        return $project->fresh(['department', 'nestedDepartment', 'majorOffice']);
    }

    protected function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Project::STATUS_VALUES)],
            'name' => 'required|string|max:255',
            'project_type' => ['nullable', Rule::in(Project::TYPE_VALUES)],
            'launch_date' => 'nullable|date',
            'department_id' => 'nullable|integer|exists:departments,id',
            'nested_department_id' => 'nullable|integer|exists:departments,id',
            'major_office_id' => 'nullable|integer|exists:major_offices,id',
            'client_pi' => 'nullable|string|max:255',
            'client_category' => ['nullable', Rule::in(Project::CLIENT_CATEGORY_VALUES)],
            'uconn_affiliation' => ['nullable', Rule::in(Project::AFFILIATION_VALUES)],
            'grant_value' => 'nullable|numeric|min:0',
            'sponsor' => 'nullable|string|max:255',
        ];
    }

    protected function normalizeIncoming(string $field, mixed $value): mixed
    {
        if ($value === '' || $value === false) {
            $value = null;
        }

        if (in_array($field, ['department_id', 'nested_department_id', 'major_office_id'], true)) {
            return $value === null ? null : (int) $value;
        }

        if ($field === 'grant_value') {
            if ($value === null) {
                return null;
            }

            $digits = preg_replace('/[^0-9.]/', '', (string) $value) ?? '';

            if ($digits === '' || $digits === '.') {
                return null;
            }

            return number_format((float) $digits, 2, '.', '');
        }

        if ($field === 'launch_date' && is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return $value;
    }

    protected function nestedBelongsToHome(?int $nestedId, mixed $homeId): bool
    {
        if (! $nestedId || ! $homeId) {
            return false;
        }

        $nested = Department::find($nestedId);

        return $nested && (int) $nested->parent_id === (int) $homeId;
    }

    protected function assertDepartmentRelationship(array $validated): array
    {
        $departmentId = $validated['department_id'] ?? null;
        $nestedId = $validated['nested_department_id'] ?? null;

        if (array_key_exists('department_id', $validated) && $departmentId) {
            $department = Department::find($departmentId);
            if (! $department || ! $department->isHomeDepartment()) {
                throw ValidationException::withMessages([
                    'department_id' => 'Home department must be a top-level department.',
                ]);
            }
        }

        if (array_key_exists('nested_department_id', $validated) && $nestedId) {
            if (! $departmentId) {
                throw ValidationException::withMessages([
                    'nested_department_id' => 'Choose a home department before selecting a nested department.',
                ]);
            }

            $nested = Department::find($nestedId);
            if (! $nested || (int) $nested->parent_id !== (int) $departmentId) {
                throw ValidationException::withMessages([
                    'nested_department_id' => 'Nested department must belong to the selected home department.',
                ]);
            }
        }

        return $validated;
    }
}
