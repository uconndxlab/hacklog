@extends('layouts.app')

@section('title', $project->name . ' - Schedule')

@section('content')
<div class="row">
    <div class="col-lg-12">
        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'schedule'])

        {{-- Page Actions --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('projects.phases.create', $project) }}" class="btn btn-sm btn-primary">
                    Create Phase
                </a>
            </div>
            <div>
                @if($showCompleted)
                    <a href="{{ route('projects.schedule', $project) }}" class="btn btn-sm btn-outline-secondary">
                        Hide Completed
                    </a>
                @else
                    <a href="{{ route('projects.schedule', ['project' => $project, 'show_completed' => '1']) }}" class="btn btn-sm btn-outline-secondary">
                        Show Completed
                    </a>
                @endif
            </div>
        </div>

        @if($project->phases->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No phases yet. Create a phase to organize your work, then add tasks with due dates to see them on the schedule.',
                'actionUrl' => route('projects.phases.create', $project),
                'actionText' => 'Create your first phase'
            ])
        @else
            @php
                $today = \Carbon\Carbon::today();
            @endphp

            @foreach($project->phases as $phase)
                <div class="card mb-4 @if($phase->status === 'completed') bg-light @endif">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="h5 mb-1 @if($phase->status === 'completed') text-muted @else fw-semibold @endif">{{ $phase->name }}</h3>
                                <div>
                                    <span class="badge 
                                        @if($phase->status === 'planned') bg-secondary
                                        @elseif($phase->status === 'active') bg-success
                                        @else bg-primary
                                        @endif">
                                        {{ ucfirst($phase->status) }}
                                    </span>
                                    @if($phase->start_date || $phase->end_date)
                                        <span class="text-muted small ms-2">
                                            @if($phase->start_date)
                                                {{ $phase->start_date->format('M j, Y') }}
                                            @endif
                                            @if($phase->start_date && $phase->end_date)
                                                →
                                            @endif
                                            @if($phase->end_date)
                                                {{ $phase->end_date->format('M j, Y') }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @php
                            // Filter tasks to only show those with effective due dates
                            $tasksWithDates = $phase->tasks->filter(function($task) {
                                return $task->getEffectiveDueDate() !== null;
                            });
                        @endphp
                        
                        @if($tasksWithDates->isEmpty())
                            <p class="text-muted small mb-0">No tasks with due dates in this phase.</p>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($tasksWithDates as $task)
                                    @php
                                        $effectiveDueDate = $task->getEffectiveDueDate();
                                        $isInheritedDate = !$task->due_date && $effectiveDueDate;
                                        $isEffectivelyOverdue = $effectiveDueDate && $effectiveDueDate->isBefore($today) && $task->status !== 'completed';
                                    @endphp
                                    <div class="list-group-item px-0 @if($isEffectivelyOverdue) border-start border-danger border-3 ps-2 @endif">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1 @if($isEffectivelyOverdue) text-danger fw-semibold @endif">
                                                    <a href="{{ route('projects.phases.tasks.show', [$project, $phase, $task]) }}" class="@if($isEffectivelyOverdue) text-danger @endif">
                                                        {{ $task->title }}
                                                    </a>
                                                </h6>
                                                <div class="small">
                                                    <span class="text-muted">{{ $task->column->name }}</span>
                                                    
                                                    @if($task->users->isNotEmpty())
                                                        <span class="mx-2 text-muted">•</span>
                                                        <span class="text-muted">{{ $task->users->pluck('name')->join(', ') }}</span>
                                                    @endif
                                                    
                                                    <span class="mx-2 text-muted">•</span>
                                                    @if($task->start_date && $task->due_date)
                                                        <span class="@if($isEffectivelyOverdue) text-danger @else text-muted @endif">
                                                            {{ $task->start_date->format('M j') }} → {{ $task->due_date->format('M j, Y') }}
                                                        </span>
                                                    @elseif($task->start_date && !$task->due_date && $effectiveDueDate)
                                                        <span class="@if($isEffectivelyOverdue) text-danger @else text-muted @endif">
                                                            {{ $task->start_date->format('M j') }} → {{ $effectiveDueDate->format('M j, Y') }} (from phase)
                                                        </span>
                                                    @elseif($task->start_date && !$effectiveDueDate)
                                                        <span class="text-muted">
                                                            Starts: {{ $task->start_date->format('M j, Y') }}
                                                        </span>
                                                    @elseif($task->due_date)
                                                        <span class="@if($isEffectivelyOverdue) text-danger @else text-muted @endif">
                                                            Due: {{ $task->due_date->format('M j, Y') }}
                                                        </span>
                                                    @elseif($isInheritedDate)
                                                        <span class="@if($isEffectivelyOverdue) text-danger @else text-muted @endif">
                                                            Due: {{ $effectiveDueDate->format('M j, Y') }} (from phase)
                                                        </span>
                                                    @endif
                                                    
                                                    @if($isEffectivelyOverdue)
                                                        <span class="badge bg-danger ms-2">Overdue</span>
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
