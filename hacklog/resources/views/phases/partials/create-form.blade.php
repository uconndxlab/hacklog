<form action="{{ route('projects.phases.store', $project) }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="phase-name" class="form-label">Phase Name</label>
        <input type="text" class="form-control" id="phase-name" name="name" value="{{ old('name') }}" required>
    </div>
    <div class="mb-3">
        <label for="phase-description" class="form-label">Description</label>
        <input id="phase-description" type="hidden" name="description" value="{{ old('description') }}">
        <trix-editor input="phase-description"></trix-editor>
    </div>
    <div class="mb-3">
        <label for="phase-status" class="form-label">Status</label>
        <select class="form-select" id="phase-status" name="status" required>
            <option value="planned" {{ old('status', 'planned') === 'planned' ? 'selected' : '' }}>Planned</option>
            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed</option>
        </select>
    </div>
    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="phase-start-date" class="form-label">Start Date</label>
            <input type="date" class="form-control" id="phase-start-date" name="start_date" value="{{ old('start_date') }}">
        </div>
        <div class="col-md-6 mb-3">
            <label for="phase-end-date" class="form-label">End Date</label>
            <input type="date" class="form-control" id="phase-end-date" name="end_date" value="{{ old('end_date') }}">
        </div>
    </div>
    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Create Phase</button>
    </div>
</form>
