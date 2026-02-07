@extends('layouts.app')

@section('title', $project->name . ' - Schedule')

@section('content')
<div class="row">
    <div class="col-lg-12">
        @include('projects.partials.project-nav', ['currentView' => 'schedule'])

        <div class="board-header mb-4">
            <div class="board-header-title">
                <h1 class="mb-0">{{ $project->name }}</h1>
            </div>
            <div class="board-header-actions">
                @if($showCompleted)
                    <a href="{{ route('projects.schedule', $project) }}" class="btn btn-sm btn-outline-secondary">
                        <span class="d-none d-md-inline">Hide Completed</span>
                        <span class="d-inline d-md-none">Hide Done</span>
                    </a>
                @else
                    <a href="{{ route('projects.schedule', ['project' => $project, 'show_completed' => '1']) }}" class="btn btn-sm btn-outline-secondary">
                        <span class="d-none d-md-inline">Show Completed</span>
                        <span class="d-inline d-md-none">Show Done</span>
                    </a>
                @endif
            </div>
        </div>

        @if($project->epics->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No epics yet. Create an epic to organize your work, then add tasks with due dates to see them on the schedule.',
                'actionUrl' => route('projects.epics.create', $project),
                'actionText' => 'Create your first epic'
            ])
        @else
            @php
                $today = \Carbon\Carbon::today();
            @endphp

            @foreach($project->epics as $epic)
                <div class="card mb-4 @if($epic->status === 'completed') bg-light @endif">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="h5 mb-1 @if($epic->status === 'completed') text-muted @else fw-semibold @endif">{{ $epic->name }}</h3>
                                <div>
                                    <span class="badge 
                                        @if($epic->status === 'planned') bg-secondary
                                        @elseif($epic->status === 'active') bg-success
                                        @else bg-primary
                                        @endif">
                                        {{ ucfirst($epic->status) }}
                                    </span>
                                    @if($epic->start_date || $epic->end_date)
                                        <span class="text-muted small ms-2">
                                            @if($epic->start_date)
                                                {{ $epic->start_date->format('M j, Y') }}
                                            @endif
                                            @if($epic->start_date && $epic->end_date)
                                                →
                                            @endif
                                            @if($epic->end_date)
                                                {{ $epic->end_date->format('M j, Y') }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @if($epic->tasks->isEmpty())
                            <p class="text-muted small mb-0">No tasks with due dates in this epic.</p>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($epic->tasks as $task)
                                    <div class="list-group-item px-0 @if($task->isOverdue()) border-start border-danger border-3 ps-2 @endif">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 @if($task->isOverdue()) text-danger fw-semibold @endif">
                                                    <a href="{{ route('projects.epics.tasks.show', [$project, $epic, $task]) }}" class="@if($task->isOverdue()) text-danger @endif">
                                                        {{ $task->title }}
                                                    </a>
                                                </h6>
                                                <div class="small">
                                                    <span class="text-muted">{{ $task->column->name }}</span>
                                                    
                                                    @if($task->users->isNotEmpty())
                                                        <span class="mx-2 text-muted">•</span>
                                                        <span class="text-muted">{{ $task->users->pluck('name')->join(', ') }}</span>
                                                    @endif
                                                    
                                                    @if($task->start_date || $task->due_date)
                                                        <span class="mx-2 text-muted">•</span>
                                                        @if($task->start_date && $task->due_date)
                                                            <span class="@if($task->isOverdue()) text-danger @else text-muted @endif">
                                                                {{ $task->start_date->format('M j') }} → {{ $task->due_date->format('M j, Y') }}
                                                            </span>
                                                        @elseif($task->start_date)
                                                            <span class="text-muted">
                                                                Starts: {{ $task->start_date->format('M j, Y') }}
                                                            </span>
                                                        @elseif($task->due_date)
                                                            <span class="@if($task->isOverdue()) text-danger @else text-muted @endif">
                                                                Due: {{ $task->due_date->format('M j, Y') }}
                                                            </span>
                                                        @endif
                                                        
                                                        @if($task->isOverdue())
                                                            <span class="badge bg-danger ms-2">Overdue</span>
                                                        @endif
                                                    @else
                                                        <span class="mx-2 text-muted">•</span>
                                                        <span class="text-muted">No dates set</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
