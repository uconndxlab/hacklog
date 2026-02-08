@extends('layouts.app')

@section('title', 'Organization Schedule')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="mb-1">Organization Schedule</h1>
                <p class="text-muted mb-0">Upcoming work across all projects</p>
            </div>
        </div>

        {{-- Filter Form --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('schedule.index') }}" class="row g-3">
                    <div class="col-md-3">
                        <label for="start" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start" name="start" value="{{ $filterStart->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="end" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end" name="end" value="{{ $filterEnd->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="project_id" class="form-label">Project</label>
                        <select class="form-select" id="project_id" name="project_id">
                            <option value="">All Projects</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="assignee" class="form-label">Assignee</label>
                        <select class="form-select" id="assignee" name="assignee">
                            <option value="">All Assignees</option>
                            <option value="unassigned" {{ request('assignee') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ request('assignee') == $user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                    <div class="col-12">
                        <a href="{{ route('schedule.index') }}" class="btn btn-sm btn-outline-secondary">Reset to Defaults</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Overdue Section --}}
        @if($overdueGrouped->isNotEmpty())
            <div class="mb-4">
                <h2 class="h4 mb-3 text-danger fw-semibold">Overdue</h2>
                @foreach($overdueGrouped as $date => $tasks)
                    <div class="card mb-3 border-danger">
                        <div class="card-header bg-danger bg-opacity-10">
                            <h3 class="h6 mb-0 text-danger fw-semibold">
                                {{ Carbon\Carbon::parse($date)->format('l, F j, Y') }}
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @foreach($tasks as $task)
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h4 class="h6 mb-1 text-danger fw-semibold">
                                                    @if($task->phase)
                                                        <a href="{{ route('projects.phases.tasks.show', [$task->phase->project, $task->phase, $task]) }}" class="text-danger text-decoration-none">
                                                            {{ $task->title }}
                                                        </a>
                                                    @else
                                                        <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-danger text-decoration-none">
                                                            {{ $task->title }}
                                                        </a>
                                                    @endif
                                                </h4>
                                                <div class="small text-muted">
                                                    <span class="fw-medium">{{ $task->getProject()->name }}</span>
                                                    @if($task->phase)
                                                        <span class="mx-1">→</span>
                                                        <span>{{ $task->phase->name }}</span>
                                                    @endif
                                                    <span class="mx-1">•</span>
                                                    <span>{{ $task->column->name }}</span>
                                                    @if($task->users->isNotEmpty())
                                                        <span class="mx-1">•</span>
                                                        <span>{{ $task->users->pluck('name')->join(', ') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="ms-3 text-end">
                                                <div class="small text-danger fw-medium">
                                                    {{ Carbon\Carbon::parse($date)->diffForHumans() }}
                                                </div>
                                                <span class="badge bg-danger">Overdue</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Upcoming Section --}}
        @if($rangeGrouped->isNotEmpty())
            <div class="mb-4">
                <h2 class="h4 mb-3">Upcoming</h2>
                @foreach($rangeGrouped as $date => $tasks)
                    @php
                        $dateObj = Carbon\Carbon::parse($date);
                        $isToday = $dateObj->isToday();
                        $isTomorrow = $dateObj->isTomorrow();
                    @endphp
                    <div class="card mb-3 @if($isToday) border-primary @endif">
                        <div class="card-header @if($isToday) bg-primary bg-opacity-10 @else bg-light @endif">
                            <h3 class="h6 mb-0 @if($isToday) text-primary @endif fw-semibold">
                                {{ $dateObj->format('l, F j, Y') }}
                                @if($isToday)
                                    <span class="badge bg-primary ms-2">Today</span>
                                @elseif($isTomorrow)
                                    <span class="badge bg-secondary ms-2">Tomorrow</span>
                                @endif
                            </h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                @foreach($tasks as $task)
                                    <div class="list-group-item @if($task->status === 'completed') bg-light @endif">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h4 class="h6 mb-1 @if($task->status === 'completed') text-muted @endif">
                                                    @if($task->phase)
                                                        <a href="{{ route('projects.phases.tasks.show', [$task->phase->project, $task->phase, $task]) }}" 
                                                           class="@if($task->status === 'completed') text-muted @else text-body @endif text-decoration-none">
                                                            {{ $task->title }}
                                                        </a>
                                                    @else
                                                        <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" 
                                                           class="@if($task->status === 'completed') text-muted @else text-body @endif text-decoration-none">
                                                            {{ $task->title }}
                                                        </a>
                                                    @endif
                                                </h4>
                                                <div class="small @if($task->status === 'completed') text-muted @else text-muted @endif">
                                                    <span class="fw-medium">{{ $task->getProject()->name }}</span>
                                                    @if($task->phase)
                                                        <span class="mx-1">→</span>
                                                        <span>{{ $task->phase->name }}</span>
                                                    @endif
                                                    <span class="mx-1">•</span>
                                                    <span>{{ $task->column->name }}</span>
                                                    @if($task->users->isNotEmpty())
                                                        <span class="mx-1">•</span>
                                                        <span>{{ $task->users->pluck('name')->join(', ') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="ms-3 text-end">
                                                <span class="badge 
                                                    @if($task->status === 'planned') bg-secondary
                                                    @elseif($task->status === 'active') bg-success
                                                    @else bg-primary
                                                    @endif">
                                                    {{ ucfirst($task->status) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Empty State --}}
        @if($overdueGrouped->isEmpty() && $rangeGrouped->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No tasks found in the selected date range. Tasks with due dates will appear here.',
            ])
        @endif
    </div>
</div>
@endsection
