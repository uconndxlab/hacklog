@extends('layouts.app')

@section('title', $project->name . ' - Schedule')

@section('content')
<div class="row">
    <div class="col-lg-12">
        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'schedule'])

        {{-- Page Actions --}}
        <div class="d-flex justify-content-between align-items-center mb-4">

        </div>

        @if($project->phases->isEmpty() && (!isset($standaloneTasks) || $standaloneTasks->isEmpty()) && (!isset($standaloneTasksNoDates) || $standaloneTasksNoDates->isEmpty()))
            @include('partials.empty-state', [
                'message' => 'No phases or tasks yet. Create a phase to organize your work, or add tasks to see them on the schedule.',
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
                            // Separate tasks into those with effective due dates and those without
                            $tasksWithDates = $phase->tasks->filter(function($task) {
                                return $task->getEffectiveDueDate() !== null;
                            });
                            $tasksWithoutDates = $phase->tasks->filter(function($task) {
                                return $task->getEffectiveDueDate() === null;
                            });
                        @endphp

                        @if($tasksWithDates->isNotEmpty())
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

                        @if($tasksWithoutDates->isNotEmpty())
                            @if($tasksWithDates->isNotEmpty())
                                <hr class="my-3">
                            @endif
                            <h6 class="text-muted mb-2">Tasks without due dates:</h6>
                            <div class="list-group list-group-flush">
                                @foreach($tasksWithoutDates as $task)
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="{{ route('projects.phases.tasks.show', [$project, $phase, $task]) }}">
                                                        {{ $task->title }}
                                                    </a>
                                                </h6>
                                                <div class="small">
                                                    <span class="text-muted">{{ $task->column->name }}</span>

                                                    @if($task->users->isNotEmpty())
                                                        <span class="mx-2 text-muted">•</span>
                                                        <span class="text-muted">{{ $task->users->pluck('name')->join(', ') }}</span>
                                                    @endif

                                                    @if($task->start_date)
                                                        <span class="mx-2 text-muted">•</span>
                                                        <span class="text-muted">
                                                            Starts: {{ $task->start_date->format('M j, Y') }}
                                                        </span>
                                                    @endif

                                                    @if($task->status === 'completed')
                                                        <span class="badge bg-success ms-2">Completed</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($tasksWithDates->isEmpty() && $tasksWithoutDates->isEmpty())
                            <p class="text-muted small mb-0">No tasks in this phase.</p>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Standalone Tasks (not attached to phases) --}}
            @if(isset($standaloneTasks) && $standaloneTasks->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="h5 mb-1 fw-semibold">Standalone Tasks</h3>
                                <div>
                                    <span class="badge bg-info">No Phase</span>
                                    <span class="text-muted small ms-2">Tasks not organized into phases</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @foreach($standaloneTasks as $task)
                                @php
                                    $effectiveDueDate = $task->getEffectiveDueDate();
                                    $isEffectivelyOverdue = $effectiveDueDate && $effectiveDueDate->isBefore($today) && $task->status !== 'completed';
                                @endphp
                                <div class="list-group-item px-0 @if($isEffectivelyOverdue) border-start border-danger border-3 ps-2 @endif">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 @if($isEffectivelyOverdue) text-danger fw-semibold @endif">
                                                <a href="{{ route('projects.board', ['project' => $project, 'task' => $task->id]) }}" class="@if($isEffectivelyOverdue) text-danger @endif">
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
                                                @elseif($task->start_date && !$task->due_date)
                                                    <span class="text-muted">
                                                        Starts: {{ $task->start_date->format('M j, Y') }}
                                                    </span>
                                                @elseif($task->due_date)
                                                    <span class="@if($isEffectivelyOverdue) text-danger @else text-muted @endif">
                                                        Due: {{ $task->due_date->format('M j, Y') }}
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
                    </div>
                </div>
            @endif

            {{-- Standalone Tasks without Due Dates --}}
            @if(isset($standaloneTasksNoDates) && $standaloneTasksNoDates->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h3 class="h5 mb-1 fw-semibold">Tasks without Due Dates</h3>
                                <div>
                                    <span class="badge bg-warning text-dark">No Deadline</span>
                                    <span class="text-muted small ms-2">Tasks that need scheduling</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @foreach($standaloneTasksNoDates as $task)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="{{ route('projects.board', ['project' => $project, 'task' => $task->id]) }}">
                                                    {{ $task->title }}
                                                </a>
                                            </h6>
                                            <div class="small">
                                                <span class="text-muted">{{ $task->column->name }}</span>

                                                @if($task->users->isNotEmpty())
                                                    <span class="mx-2 text-muted">•</span>
                                                    <span class="text-muted">{{ $task->users->pluck('name')->join(', ') }}</span>
                                                @endif

                                                @if($task->start_date)
                                                    <span class="mx-2 text-muted">•</span>
                                                    <span class="text-muted">
                                                        Starts: {{ $task->start_date->format('M j, Y') }}
                                                    </span>
                                                @endif

                                                @if($task->status === 'completed')
                                                    <span class="badge bg-success ms-2">Completed</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
