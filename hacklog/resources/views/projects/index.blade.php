@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">Projects</h1>
        <small class="text-muted">
            @if(request('assigned') === 'all')
                Showing all projects
            @else
                Showing projects assigned to you
            @endif
        </small>
    </div>
    <a href="{{ route('projects.create') }}" class="btn btn-primary">New Project</a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('projects.index') }}" class="d-flex align-items-center gap-3 flex-wrap">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="assignedFilter" name="assigned" value="all"
                       {{ request('assigned') === 'all' ? 'checked' : '' }}>
                <label class="form-check-label" for="assignedFilter">
                    Show all projects
                </label>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <label for="status" class="form-label mb-0">Status:</label>
                <select class="form-select form-select-sm" id="status" name="status" style="width: auto;">
                    <option value="">All Active</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>Paused</option>
                    <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary btn-sm">Apply</button>
                <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

@if($projects->isEmpty())
    @include('partials.empty-state', [
        'message' => 'No projects assigned to you yet. Projects are top-level containers for organizing your work into epics and tasks.',
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
