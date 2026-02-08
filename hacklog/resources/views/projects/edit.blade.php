@extends('layouts.app')

@section('title', $project->name . ' - Settings')

@section('content')
<div class="row">
    <div class="col-lg-12">
        @include('projects.partials.project-nav', ['currentView' => 'settings'])

        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
                <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
                <li class="breadcrumb-item active" aria-current="page">Settings</li>
            </ol>
        </nav>

        <h1 class="mb-4">{{ $project->name }} Settings</h1>

        {{-- Project Details Section --}}
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0">Project Details</h2>
            </div>
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

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>
            </div>
        </div>

        {{-- Phases Management Section --}}
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0">Phases Management</h2>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Phases organize your work into major features or initiatives. Create and manage phases for this project.</p>
                <div class="d-flex gap-2">
                    <a href="{{ route('projects.phases.create', $project) }}" class="btn btn-outline-primary">Create New Phase</a>
                    <a href="{{ route('projects.phases.index', $project) }}" class="btn btn-outline-secondary">View All Phases</a>
                </div>
            </div>
        </div>

        {{-- Columns Configuration Section --}}
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h2 class="h5 mb-0">Board Columns</h2>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Configure the columns that appear on your kanban board. Columns represent workflow stages for tasks.</p>
                <a href="{{ route('projects.columns.index', $project) }}" class="btn btn-outline-secondary">Manage Columns</a>
            </div>
        </div>

        {{-- Danger Zone Section --}}
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h2 class="h5 mb-0">Danger Zone</h2>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">Deleting this project will permanently remove all phases, tasks, and associated data. This action cannot be undone.</p>
                <form action="{{ route('projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this project? All phases and tasks will be permanently deleted.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Project</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
