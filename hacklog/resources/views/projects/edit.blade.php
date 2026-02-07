@extends('layouts.app')

@section('title', 'Edit Project')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <h1 class="mb-4">Edit Project</h1>

        <div class="card mb-4">
            <div class="card-body">
                <form action="{{ route('projects.update', $project) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name" class="form-label">Project Name</label>
                <input 
                    type="text" 
                    class="form-control @error('name') is-invalid @enderror" 
                    id="name" 
                    name="name" 
                    value="{{ old('name', $project->name) }}" 
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
                    value="{{ old('description', $project->description) }}">
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
                    <option value="active" {{ old('status', $project->status) === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="paused" {{ old('status', $project->status) === 'paused' ? 'selected' : '' }}>Paused</option>
                    <option value="archived" {{ old('status', $project->status) === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Project</button>
                <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
            </div>
        </div>

        <h2 class="h5 mb-3 text-danger">Danger Zone</h2>
        <div class="card border-danger">
            <div class="card-body">
                <p class="text-muted mb-3">Deleting this project will permanently remove all epics, tasks, and associated data. This action cannot be undone.</p>
                <form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this project? All epics and tasks will be permanently deleted.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Project</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
