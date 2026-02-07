@extends('layouts.app')

@section('title', $epic->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.epics.index', $project) }}">Epics</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $epic->name }}</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1>{{ $epic->name }}</h1>
        <p class="text-muted mb-0">
            <span class="badge 
                @if($epic->status === 'planned') bg-secondary
                @elseif($epic->status === 'active') bg-success
                @else bg-primary
                @endif">
                {{ ucfirst($epic->status) }}
            </span>
        </p>
    </div>
    <a href="{{ route('projects.epics.edit', [$project, $epic]) }}" class="btn btn-secondary">Edit Epic</a>
</div>

<h2 class="h4 mb-3">Description</h2>
<div class="card mb-4">
    <div class="card-body">
        @if($epic->description)
            <div class="trix-content">
                {!! $epic->description !!}
            </div>
        @else
            <p class="text-muted mb-0">No description provided.</p>
        @endif
    </div>
</div>

<h2 class="h4 mb-3">Tasks</h2>
<div class="card mb-4">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0 fw-semibold">Kanban Board</h3>
        <div class="d-flex gap-2">
            @can('admin')
                <a href="{{ route('admin.epics.tasks.index', [$project, $epic]) }}" class="btn btn-sm btn-outline-danger">Admin: Cleanup Tasks</a>
            @endcan
            <a href="{{ route('projects.epics.tasks.create', [$project, $epic]) }}" class="btn btn-sm btn-primary">Create Task</a>
        </div>
    </div>
    <div class="card-body">
        @if($columns->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No kanban columns defined for this project. Set up your workflow by creating columns first.',
                'actionUrl' => route('projects.columns.index', $project),
                'actionText' => 'Manage columns'
            ])
        @elseif($tasks->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No tasks yet. Break down this epic into actionable tasks.',
                'actionUrl' => route('projects.epics.tasks.create', [$project, $epic]),
                'actionText' => 'Create your first task'
            ])
        @else
            <div class="row g-3">
                @foreach($columns as $column)
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        @include('projects.partials.board-column', [
                            'project' => $project,
                            'column' => $column,
                            'columnTasks' => $tasks->get($column->id, collect()),
                            'allColumns' => $columns,
                            'isProjectBoard' => false
                        ])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<h2 class="h4 mb-3">Details</h2>
<div class="card mb-4">
    <div class="card-header bg-light">
        <h3 class="h6 mb-0 fw-semibold">Epic Information</h3>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Project</dt>
            <dd class="col-sm-9">
                <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
            </dd>

            @if($epic->start_date)
                <dt class="col-sm-3">Start Date</dt>
                <dd class="col-sm-9">{{ $epic->start_date->format('F j, Y') }}</dd>
            @endif

            @if($epic->end_date)
                <dt class="col-sm-3">End Date</dt>
                <dd class="col-sm-9">{{ $epic->end_date->format('F j, Y') }}</dd>
            @endif

            <dt class="col-sm-3">Created</dt>
            <dd class="col-sm-9">{{ $epic->created_at->format('F j, Y \a\t g:i A') }}</dd>

            <dt class="col-sm-3">Last Updated</dt>
            <dd class="col-sm-9 mb-0">{{ $epic->updated_at->format('F j, Y \a\t g:i A') }}</dd>
        </dl>
    </div>
</div>

<div class="mt-3">
    <a href="{{ route('projects.epics.index', $project) }}" class="btn btn-outline-secondary">Back to Epics</a>
</div>

@push('scripts')
<style>
    /* Basic styling for Trix content display */
    .trix-content {
        line-height: 1.6;
    }
    .trix-content h1 {
        font-size: 1.5rem;
        margin-top: 1rem;
        margin-bottom: 0.5rem;
    }
    .trix-content p {
        margin-bottom: 0.75rem;
    }
    .trix-content ul, .trix-content ol {
        margin-bottom: 0.75rem;
        padding-left: 2rem;
    }
    .trix-content blockquote {
        border-left: 4px solid #dee2e6;
        padding-left: 1rem;
        margin-left: 0;
        color: #6c757d;
    }
</style>
@endpush
@endsection
