@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Projects</h1>
    <a href="{{ route('projects.create') }}" class="btn btn-primary">New Project</a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('projects.index') }}" class="row g-3">
            <div class="col-md-6">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="assignedFilter" name="assigned" value="me"
                           {{ request('assigned') === 'me' ? 'checked' : '' }}>
                    <label class="form-check-label" for="assignedFilter">
                        Assigned to Me
                    </label>
                </div>
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Active</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>Paused</option>
                    <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">Apply</button>
                <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

@if($projects->isEmpty())
    @include('partials.empty-state', [
        'message' => 'No projects yet. Projects are top-level containers for organizing your work into epics and tasks.',
        'actionUrl' => route('projects.create'),
        'actionText' => 'Create your first project'
    ])
@else
    <div class="row">
        @foreach($projects as $project)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5 card-title mb-2">{{ $project->name }}</h2>
                        <div class="mb-2">
                            <span class="badge 
                                @if($project->status === 'active') bg-success
                                @elseif($project->status === 'paused') bg-warning text-dark
                                @else bg-secondary
                                @endif">
                                {{ ucfirst($project->status) }}
                            </span>
                        </div>
                        @if($project->description)
                            <p class="card-text text-muted small">
                                {{ Str::limit(strip_tags($project->description), 100) }}
                            </p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-primary">View</a>
                        <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
