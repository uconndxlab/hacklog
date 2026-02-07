@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="row">
    <div class="col-lg-10">
        @include('projects.partials.project-nav', ['currentView' => 'home'])

        {{-- Project Header --}}
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h1 class="mb-2">{{ $project->name }}</h1>
                    <div>
                        <span class="badge 
                            @if($project->status === 'active') bg-success
                            @elseif($project->status === 'paused') bg-warning text-dark
                            @else bg-secondary
                            @endif">
                            {{ ucfirst($project->status) }}
                        </span>
                        <span class="text-muted small ms-2">
                            Created {{ $project->created_at->format('M j, Y') }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-secondary">Edit Project</a>
            </div>
            
            @if($project->description)
                <div class="mt-3">
                    <div class="trix-content text-muted">
                        {!! $project->description !!}
                    </div>
                </div>
            @endif
        </div>

        {{-- Quick Actions --}}
        <div class="mb-4">
            <a href="{{ route('projects.epics.create', $project) }}" class="btn btn-outline-primary">Create Epic</a>
            <a href="{{ route('projects.epics.index', $project) }}" class="btn btn-outline-secondary">Manage Epics</a>
            <a href="{{ route('projects.columns.index', $project) }}" class="btn btn-outline-secondary">Manage Columns</a>
        </div>

        {{-- Project Health Summary --}}
        <h2 class="h4 mb-3">Project Health</h2>
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h3 class="h6 mb-0 fw-semibold">Current Status</h3>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="mb-2">
                            <span class="fs-2 fw-semibold">{{ $activeEpicsCount }}</span>
                        </div>
                        <div class="text-muted small">Active Epics</div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-2">
                            <span class="fs-2 fw-semibold @if($overdueTasks > 0) text-danger @endif">{{ $overdueTasks }}</span>
                        </div>
                        <div class="text-muted small">
                            @if($overdueTasks > 0)
                                <span class="text-danger">Overdue Tasks</span>
                            @else
                                Overdue Tasks
                            @endif
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-2">
                            @if($nearestDueDate)
                                <span class="fs-5 fw-semibold">{{ $nearestDueDate->format('M j') }}</span>
                            @else
                                <span class="fs-5 text-muted">—</span>
                            @endif
                        </div>
                        <div class="text-muted small">Next Due Date</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                {{-- Upcoming Work --}}
                <h2 class="h4 mb-3">Upcoming Work</h2>
                <div class="card mb-4">
                    <div class="card-body">
                        @if($upcomingTasks->isEmpty())
                            @include('partials.empty-state', [
                                'message' => 'No upcoming tasks with due dates. Tasks will appear here once they have due dates assigned.',
                            ])
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($upcomingTasks as $task)
                                    <div class="list-group-item px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="mb-1">
                                                    <a href="{{ route('projects.epics.tasks.show', [$project, $task->epic, $task]) }}" 
                                                       class="text-decoration-none @if($task->isOverdue()) text-danger fw-semibold @endif">
                                                        {{ $task->title }}
                                                    </a>
                                                </div>
                                                <div class="small text-muted">
                                                    {{ $task->epic->name }}
                                                    <span class="mx-1">•</span>
                                                    <span class="@if($task->isOverdue()) text-danger @endif">
                                                        {{ $task->due_date->format('M j, Y') }}
                                                    </span>
                                                    @if($task->isOverdue())
                                                        <span class="badge bg-danger ms-1">Overdue</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @if($upcomingTasks->count() >= 5)
                                <div class="mt-2">
                                    <a href="{{ route('projects.schedule', $project) }}" class="btn btn-sm btn-outline-secondary">View Full Schedule</a>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                {{-- Epics Snapshot --}}
                <h2 class="h4 mb-3">Active Epics</h2>
                <div class="card mb-4">
                    <div class="card-body">
                        @if($project->epics->isEmpty())
                            @include('partials.empty-state', [
                                'message' => 'No active epics. Create an epic to organize your work into larger features or phases.',
                                'actionUrl' => route('projects.epics.create', $project),
                                'actionText' => 'Create your first epic'
                            ])
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($project->epics as $epic)
                                    <div class="list-group-item px-0 py-2">
                                        <div class="mb-1">
                                            <a href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}" 
                                               class="text-decoration-none">
                                                {{ $epic->name }}
                                            </a>
                                        </div>
                                        <div class="small">
                                            <span class="badge 
                                                @if($epic->status === 'planned') bg-secondary
                                                @elseif($epic->status === 'active') bg-success
                                                @else bg-primary
                                                @endif">
                                                {{ ucfirst($epic->status) }}
                                            </span>
                                            @if($epic->start_date || $epic->end_date)
                                                <span class="text-muted ms-2">
                                                    @if($epic->start_date && $epic->end_date)
                                                        {{ $epic->start_date->format('M j') }} → {{ $epic->end_date->format('M j, Y') }}
                                                    @elseif($epic->start_date)
                                                        Starts {{ $epic->start_date->format('M j, Y') }}
                                                    @else
                                                        Due {{ $epic->end_date->format('M j, Y') }}
                                                    @endif
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="mt-4">
            <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">Back to Projects</a>
        </div>
    </div>
</div>

@push('scripts')
<style>
    /* Basic styling for Trix content display */
    .trix-content {
        line-height: 1.6;
        font-size: 0.95rem;
    }
    .trix-content h1 {
        font-size: 1.25rem;
        margin-top: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .trix-content p {
        margin-bottom: 0.5rem;
    }
    .trix-content ul, .trix-content ol {
        margin-bottom: 0.5rem;
        padding-left: 1.5rem;
    }
    .trix-content blockquote {
        border-left: 3px solid #dee2e6;
        padding-left: 0.75rem;
        margin-left: 0;
        color: #6c757d;
    }
</style>
@endpush
@endsection
