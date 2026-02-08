@extends('layouts.app')

@section('title', $task->title)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.phases.index', $project) }}">Phases</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}">{{ $phase->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $task->title }}</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1>{{ $task->title }}</h1>
                <p class="text-muted mb-0">
                    <span class="badge 
                        @if($task->status === 'planned') bg-secondary
                        @elseif($task->status === 'active') bg-success
                        @else bg-primary
                        @endif">
                        {{ ucfirst($task->status) }}
                    </span>
                </p>
            </div>
            <a href="{{ route('projects.phases.tasks.edit', [$project, $phase, $task]) }}" class="btn btn-secondary">Edit Task</a>
        </div>

        <h2 class="h4 mb-3">Description</h2>
        <div class="card mb-4">
            <div class="card-body">
                @if($task->description)
                    <div class="trix-content">
                        {!! $task->description !!}
                    </div>
                @else
                    <p class="text-muted mb-0">No description provided.</p>
                @endif
            </div>
        </div>

        <h2 class="h4 mb-3">Details</h2>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h3 class="h6 mb-0 fw-semibold">Task Information</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Phase</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}">{{ $phase->name }}</a>
                    </dd>

                    <dt class="col-sm-3">Project</dt>
                    <dd class="col-sm-9">
                        <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                    </dd>

                    <dt class="col-sm-3">Column</dt>
                    <dd class="col-sm-9">{{ $task->column->name }}</dd>

                    @if($task->users->isNotEmpty())
                        <dt class="col-sm-3">Assigned To</dt>
                        <dd class="col-sm-9">{{ $task->users->pluck('name')->join(', ') }}</dd>
                    @endif

                    @if($task->start_date)
                        <dt class="col-sm-3">Start Date</dt>
                        <dd class="col-sm-9">{{ $task->start_date->format('F j, Y') }}</dd>
                    @endif

                    @if($task->due_date)
                        <dt class="col-sm-3">Due Date</dt>
                        <dd class="col-sm-9">{{ $task->due_date->format('F j, Y') }}</dd>
                    @endif

                    <dt class="col-sm-3">Created</dt>
                    <dd class="col-sm-9">{{ $task->created_at->format('F j, Y \a\t g:i A') }}</dd>

                    <dt class="col-sm-3">Last Updated</dt>
                    <dd class="col-sm-9 mb-0">{{ $task->updated_at->format('F j, Y \a\t g:i A') }}</dd>
                </dl>
            </div>
        </div>

        <div class="mt-3">
            <a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}" class="btn btn-outline-secondary">Back to Board</a>
        </div>
    </div>
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
