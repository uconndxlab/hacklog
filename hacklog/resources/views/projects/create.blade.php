@extends('layouts.app')

@section('title', 'Create Project')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <h1 class="mb-4">Create Project</h1>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('projects.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label">Project Name</label>
                <input 
                    type="text" 
                    class="form-control @error('name') is-invalid @enderror" 
                    id="name" 
                    name="name" 
                    value="{{ old('name') }}" 
                    required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <input 
                    id="description" 
                    type="hidden" 
                    name="description" 
                    value="{{ old('description') }}">
                <trix-editor input="description" class="@error('description') is-invalid @enderror"></trix-editor>
                @error('description')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select 
                    class="form-select @error('status') is-invalid @enderror" 
                    id="status" 
                    name="status" 
                    required>
                    <option value="planning" {{ old('status') === 'planning' ? 'selected' : '' }}>Planning</option>
                    <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="on_hold" {{ old('status') === 'on_hold' ? 'selected' : '' }}>On Hold</option>
                    <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="archived" {{ old('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="staffing_model" class="form-label">Staffing Model</label>
                <select 
                    class="form-select @error('staffing_model') is-invalid @enderror" 
                    id="staffing_model" 
                    name="staffing_model" 
                    required>
                    <option value="dedicated" {{ old('staffing_model', 'dedicated') === 'dedicated' ? 'selected' : '' }}>Dedicated Team</option>
                    <option value="shared" {{ old('staffing_model') === 'shared' ? 'selected' : '' }}>Shared</option>
                </select>
                <div class="form-text">
                    Dedicated Team: Tasks remain within the project team. Shared: Unassigned tasks appear in the global task feed.
                </div>
                @error('staffing_model')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input 
                        class="form-check-input" 
                        type="checkbox" 
                        id="use_default_columns" 
                        name="use_default_columns" 
                        value="1"
                        {{ old('use_default_columns', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="use_default_columns">
                        Use default columns
                    </label>
                    <div class="form-text">
                        Creates standard columns: Backlog, In Progress, Ready for Testing, Completed
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Create Project</button>
                <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
            </div>
        </div>
    </div>
</div>
@endsection
