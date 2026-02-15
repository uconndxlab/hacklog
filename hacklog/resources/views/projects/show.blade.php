@extends('layouts.app')

@section('title', $project->name)

@section('content')
<div class="row">
    <div class="col-lg-12">
        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'home'])

        @if($project->description)
            <div class="mb-4">
                <div class="trix-content text-muted">
                    {!! $project->description !!}
                </div>
            </div>
        @endif

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
                            <span class="fs-2 fw-semibold">{{ $activePhasesCount }}</span>
                        </div>
                        <div class="text-muted small">Active Phases</div>
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
                                                    <a href="{{ route('projects.board', ['project' => $project, 'task' => $task->id]) }}" 
                                                       class="text-decoration-none @if($task->isOverdue()) text-danger fw-semibold @endif">
                                                        {{ $task->title }}
                                                    </a>
                                                </div>
                                                <div class="small text-muted">
                                                    @if($task->phase)
                                                        {{ $task->phase->name }}
                                                    @else
                                                        <em>No Phase</em>
                                                    @endif
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
                {{-- Awaiting Feedback --}}
                @if($awaitingFeedbackTasks->isNotEmpty())
                    <h2 class="h4 mb-3">Awaiting Feedback</h2>
                    <div class="card mb-4 border-warning">
                        <div class="card-header bg-warning bg-opacity-10">
                            <h3 class="h6 mb-0 fw-semibold">Review Needed</h3>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                @foreach($awaitingFeedbackTasks as $task)
                                    <div class="list-group-item px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="mb-1">
                                                    <a href="{{ route('projects.board', ['project' => $project, 'task' => $task->id]) }}" 
                                                       class="text-decoration-none">
                                                        {{ $task->title }}
                                                    </a>
                                                </div>
                                                <div class="small text-muted">
                                                    @if($task->phase)
                                                        {{ $task->phase->name }}
                                                    @else
                                                        <em>No Phase</em>
                                                    @endif
                                                    <span class="mx-1">•</span>
                                                    <span class="text-muted">
                                                        Updated {{ $task->updated_at->diffForHumans() }}
                                                    </span>
                                                </div>
                                            </div>
                                            <span class="badge bg-warning text-dark">{{ $task->status_display }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Phases Snapshot --}}
                <h2 class="h4 mb-3">Active Phases</h2>
                <div class="card mb-4">
                    <div class="card-body">
                        @if($project->phases->isEmpty())
                            @include('partials.empty-state', [
                                'message' => 'No active phases. Create a phase to organize your work into larger features or phases.',
                                'actionUrl' => route('projects.phases.create', $project),
                                'actionText' => 'Create your first phase'
                            ])
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($project->phases as $phase)
                                    <div class="list-group-item px-0 py-2">
                                        <div class="mb-1">
                                            <a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}" 
                                               class="text-decoration-none">
                                                {{ $phase->name }}
                                            </a>
                                        </div>
                                        <div class="small">
                                            <span class="badge 
                                                @if($phase->status === 'planned') bg-secondary
                                                @elseif($phase->status === 'active') bg-success
                                                @else bg-primary
                                                @endif">
                                                {{ ucfirst($phase->status) }}
                                            </span>
                                            @if($phase->start_date || $phase->end_date)
                                                <span class="text-muted ms-2">
                                                    @if($phase->start_date && $phase->end_date)
                                                        {{ $phase->start_date->format('M j') }} → {{ $phase->end_date->format('M j, Y') }}
                                                    @elseif($phase->start_date)
                                                        Starts {{ $phase->start_date->format('M j, Y') }}
                                                    @else
                                                        Due {{ $phase->end_date->format('M j, Y') }}
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
