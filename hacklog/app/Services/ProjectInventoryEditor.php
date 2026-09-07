<?php

namespace App\Services;

use App\Models\Department;
use App\Models\MajorOffice;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        'has_grant',
        'grant_value',
        'sponsor',
        'team_user_ids',
        'leader_user_id',
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
            'statuses' => Project::statusDefinitions()
                ->map(fn ($status) => [
                    'value' => $status->key,
                    'label' => $status->name,
                    'color' => $status->color,
                    'text_color' => $status->textColor(),
                ])
                ->values()
                ->all(),
            'types' => collect(Project::typeLabels())
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
            'teamUsers' => User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_TEAM])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'active' => (bool) $user->active,
                ])
                ->values()
                ->all(),
        ];
    }

    public function toRow(Project $project): array
    {
        $project->loadMissing('shares.user');

        $team = $project->shares
            ->filter(fn ($share) => $share->isUserShare() && $share->user && ! $share->user->isClient())
            ->sortBy(fn ($share) => [$share->is_leader ? 0 : 1, mb_strtolower($share->user->name)])
            ->map(fn ($share) => [
                'id' => $share->user->id,
                'name' => $share->user->name,
                'is_leader' => (bool) $share->is_leader,
            ])
            ->values();
        $leader = $team->firstWhere('is_leader', true);

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
            'has_grant' => (bool) $project->has_grant,
            'grant_value' => $project->grant_value !== null ? (float) $project->grant_value : null,
            'sponsor' => $project->sponsor,
            'team_user_ids' => $team->pluck('id')->all(),
            'team' => $team->all(),
            'leader_user_id' => $leader['id'] ?? null,
            'leader' => $leader ? ['id' => $leader['id'], 'name' => $leader['name']] : null,
        ];
    }

    public function apply(Project $project, string $field, mixed $value, ?int $userId): Project
    {
        if (! in_array($field, self::FIELDS, true)) {
            throw ValidationException::withMessages([
                'field' => 'That column is not editable.',
            ]);
        }

        if ($field === 'team_user_ids') {
            return $this->applyTeam($project, $value, $userId);
        }

        if ($field === 'leader_user_id') {
            return $this->applyLeader($project, $value, $userId);
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

        if ($field === 'grant_value' && $normalized !== null) {
            $payload['has_grant'] = true;
        }

        $rules = array_intersect_key($this->rules(), $payload);
        $validated = Validator::make($payload, $rules)->validate();
        $validated = $this->assertDepartmentRelationship($validated);

        $changed = array_intersect_key($validated, array_flip(array_merge(self::FIELDS, ['has_grant'])));
        $project->fill($changed);
        $project->save();

        ProjectActivity::log($project->id, $userId, 'updated', [
            'source' => 'inventory_editor',
            'field' => $field,
        ]);

        return $project->fresh(['department', 'nestedDepartment', 'majorOffice', 'shares.user']);
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

        return $project->fresh(['department', 'nestedDepartment', 'majorOffice', 'shares.user']);
    }

    protected function applyTeam(Project $project, mixed $value, ?int $userId): Project
    {
        $validated = Validator::make(
            ['team_user_ids' => $value],
            ['team_user_ids' => 'present|array', 'team_user_ids.*' => 'integer|distinct']
        )->validate();
        $ids = collect($validated['team_user_ids'])->map(fn ($id) => (int) $id)->values();
        $existingIds = $project->shares()
            ->where('shareable_type', 'user')
            ->pluck('shareable_id')
            ->map(fn ($id) => (int) $id);
        $eligibleIds = User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_TEAM])
            ->whereIn('id', $ids)
            ->where(function ($query) use ($existingIds) {
                $query->where('active', true)
                    ->when($existingIds->isNotEmpty(), fn ($query) => $query->orWhereIn('id', $existingIds));
            })
            ->pluck('id');

        if ($eligibleIds->count() !== $ids->unique()->count()) {
            throw ValidationException::withMessages([
                'value' => 'Project team members must be active admins or team users.',
            ]);
        }

        DB::transaction(function () use ($project, $eligibleIds, $userId): void {
            $internalUserIds = User::query()
                ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_TEAM])
                ->pluck('id')
                ->map(fn ($id) => (string) $id);
            $currentLeaderId = $project->shares()
                ->where('shareable_type', 'user')
                ->where('is_leader', true)
                ->value('shareable_id');

            $project->shares()
                ->where('shareable_type', 'user')
                ->whereIn('shareable_id', $internalUserIds)
                ->when($eligibleIds->isNotEmpty(), fn ($query) => $query->whereNotIn('shareable_id', $eligibleIds))
                ->delete();

            foreach ($eligibleIds as $id) {
                $project->shares()->updateOrCreate(
                    ['shareable_type' => 'user', 'shareable_id' => (string) $id],
                    ['is_leader' => (string) $id === (string) $currentLeaderId]
                );
            }

            ProjectActivity::log($project->id, $userId, 'updated', [
                'source' => 'inventory_editor',
                'field' => 'team_user_ids',
            ]);
        });

        return $project->fresh(['department', 'nestedDepartment', 'majorOffice', 'shares.user']);
    }

    protected function applyLeader(Project $project, mixed $value, ?int $userId): Project
    {
        $leaderId = ($value === null || $value === '') ? null : (int) $value;

        if ($leaderId !== null && ! User::query()
            ->whereKey($leaderId)
            ->where('active', true)
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_TEAM])
            ->exists()) {
            throw ValidationException::withMessages([
                'value' => 'Project lead must be an active admin or team user.',
            ]);
        }

        DB::transaction(function () use ($project, $leaderId, $userId): void {
            $project->shares()->where('is_leader', true)->update(['is_leader' => false]);

            if ($leaderId !== null) {
                $project->shares()->updateOrCreate(
                    ['shareable_type' => 'user', 'shareable_id' => (string) $leaderId],
                    ['is_leader' => true]
                );
            }

            ProjectActivity::log($project->id, $userId, 'updated', [
                'source' => 'inventory_editor',
                'field' => 'leader_user_id',
            ]);
        });

        return $project->fresh(['department', 'nestedDepartment', 'majorOffice', 'shares.user']);
    }

    protected function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Project::statusValues())],
            'name' => 'required|string|max:255',
            'project_type' => ['nullable', Rule::in(Project::typeValues())],
            'launch_date' => 'nullable|date',
            'department_id' => 'nullable|integer|exists:departments,id',
            'nested_department_id' => 'nullable|integer|exists:departments,id',
            'major_office_id' => 'nullable|integer|exists:major_offices,id',
            'client_pi' => 'nullable|string|max:255',
            'client_category' => ['nullable', Rule::in(Project::CLIENT_CATEGORY_VALUES)],
            'uconn_affiliation' => ['nullable', Rule::in(Project::AFFILIATION_VALUES)],
            'has_grant' => 'boolean',
            'grant_value' => 'nullable|numeric|min:0',
            'sponsor' => 'nullable|string|max:255',
        ];
    }

    protected function normalizeIncoming(string $field, mixed $value): mixed
    {
        if ($field === 'has_grant') {
            if (is_bool($value)) {
                return $value;
            }

            if (is_string($value)) {
                $value = Str::lower(trim($value));

                return in_array($value, ['1', 'true', 'yes', 'y'], true);
            }

            return (bool) $value;
        }

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
