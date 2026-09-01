@php
    $project = $project ?? null;
    $selectedDepartmentId = old('department_id', $project?->department_id);
    $selectedNestedId = old('nested_department_id', $project?->nested_department_id);
    $selectedMajorOfficeId = old('major_office_id', $project?->major_office_id);
@endphp

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="project_type" class="form-label">Project Type</label>
        <select
            class="form-select @error('project_type') is-invalid @enderror"
            id="project_type"
            name="project_type">
            <option value="">Select type…</option>
            @foreach(\App\Models\Project::TYPE_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('project_type', $project?->project_type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('project_type')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="major_office_id" class="form-label">Top Level School or Major Office</label>
        <select
            class="form-select @error('major_office_id') is-invalid @enderror"
            id="major_office_id"
            name="major_office_id">
            <option value="">Select office…</option>
            @foreach($majorOffices as $office)
                <option value="{{ $office->id }}" @selected((string) $selectedMajorOfficeId === (string) $office->id)>{{ $office->name }}</option>
            @endforeach
        </select>
        <div class="form-text">
            Manage the list in
            <a href="{{ route('major-offices.index') }}">Major Offices</a>.
        </div>
        @error('major_office_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="department_id" class="form-label">Home Department</label>
        <select
            class="form-select @error('department_id') is-invalid @enderror"
            id="department_id"
            name="department_id"
            hx-get="{{ route('departments.nested-options') }}"
            hx-target="#nested_department_id"
            hx-include="[name='department_id']"
            hx-swap="innerHTML"
            hx-on::after-swap="document.getElementById('nested_department_id').disabled = !this.value">
            <option value="">Select department…</option>
            @foreach($homeDepartments as $department)
                <option value="{{ $department->id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </select>
        <div class="form-text">
            Manage the list in
            <a href="{{ route('departments.index') }}">Departments</a>.
        </div>
        @error('department_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="nested_department_id" class="form-label">Nested Department</label>
        <select
            class="form-select @error('nested_department_id') is-invalid @enderror"
            id="nested_department_id"
            name="nested_department_id"
            @disabled(!$selectedDepartmentId)>
            @include('departments.partials.nested-options', [
                'nestedDepartments' => $nestedDepartments,
                'selectedNestedId' => $selectedNestedId,
                'departmentSelected' => (bool) $selectedDepartmentId,
            ])
        </select>
        @error('nested_department_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="client_pi" class="form-label">Client / PI</label>
        <input
            type="text"
            class="form-control @error('client_pi') is-invalid @enderror"
            id="client_pi"
            name="client_pi"
            value="{{ old('client_pi', $project?->client_pi ?? '') }}"
            maxlength="255">
        @error('client_pi')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 mb-3">
        <label for="client_category" class="form-label">Client Category</label>
        <select
            class="form-select @error('client_category') is-invalid @enderror"
            id="client_category"
            name="client_category">
            <option value="">Select category…</option>
            @foreach(\App\Models\Project::CLIENT_CATEGORY_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('client_category', $project?->client_category) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('client_category')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label for="uconn_affiliation" class="form-label">UConn Affiliation</label>
        <select
            class="form-select @error('uconn_affiliation') is-invalid @enderror"
            id="uconn_affiliation"
            name="uconn_affiliation">
            <option value="">Select affiliation…</option>
            @foreach(\App\Models\Project::AFFILIATION_LABELS as $value => $label)
                <option value="{{ $value }}" @selected(old('uconn_affiliation', $project?->uconn_affiliation) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('uconn_affiliation')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="grant_value" class="form-label">Grant Value</label>
        <div class="input-group">
            <span class="input-group-text">$</span>
            <input
                type="number"
                step="0.01"
                min="0"
                class="form-control @error('grant_value') is-invalid @enderror"
                id="grant_value"
                name="grant_value"
                value="{{ old('grant_value', $project?->grant_value ?? '') }}"
                placeholder="Leave blank if none">
        </div>
        @error('grant_value')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4 mb-3">
        <label for="sponsor" class="form-label">Sponsor</label>
        <input
            type="text"
            class="form-control @error('sponsor') is-invalid @enderror"
            id="sponsor"
            name="sponsor"
            value="{{ old('sponsor', $project?->sponsor ?? '') }}"
            maxlength="255">
        @error('sponsor')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
