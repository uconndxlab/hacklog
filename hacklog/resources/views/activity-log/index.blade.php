@extends('layouts.app')

@section('title', 'Activity Log')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Organization Activity Log</h1>
        </div>

        {{-- Filter Form --}}
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('activity-log.index') }}" class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label for="start" class="form-label mb-0 text-nowrap">From</label>
                        <input type="date" class="form-control form-control-sm" id="start" name="start" value="{{ $filterStart->format('Y-m-d') }}" onchange="this.form.submit()">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="end" class="form-label mb-0 text-nowrap">To</label>
                        <input type="date" class="form-control form-control-sm" id="end" name="end" value="{{ $filterEnd->format('Y-m-d') }}" onchange="this.form.submit()">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="project_id" class="form-label mb-0 text-nowrap">Project</label>
                        <select class="form-select form-select-sm" id="project_id" name="project_id" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" {{ request('project_id') == $project->id ? 'selected' : '' }}>
                                    {{ $project->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="user_id" class="form-label mb-0 text-nowrap">User</label>
                        <select class="form-select form-select-sm" id="user_id" name="user_id" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ (string)request('user_id') === (string)$user->id ? 'selected' : '' }}>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="type" class="form-label mb-0 text-nowrap">Type</label>
                        <select class="form-select form-select-sm" id="type" name="type" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="project" {{ request('type') === 'project' ? 'selected' : '' }}>Project</option>
                            <option value="task" {{ request('type') === 'task' ? 'selected' : '' }}>Task</option>
                            <option value="comment" {{ request('type') === 'comment' ? 'selected' : '' }}>Comment</option>
                        </select>
                    </div>
                    <a href="{{ route('activity-log.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-4">
                    Showing activities from {{ $filterStart->format('M j, Y') }} to {{ $filterEnd->format('M j, Y') }}
                    @if(request('project_id'))
                        for {{ $projects->firstWhere('id', request('project_id'))->name }}
                    @endif
                    @if(request('user_id'))
                        by {{ $users->firstWhere('id', request('user_id'))->name }}
                    @endif
                    (up to 200 most recent entries)
                </p>

                @if($allActivities->isEmpty())
                    <div class="text-center text-muted py-5">
                        <p>No activity recorded for the selected filters.</p>
                    </div>
                @else
                    <div class="activity-timeline">
                        @php
                            $lastDate = null;
                        @endphp
                        @foreach($allActivities as $activity)
                            @php
                                $currentDate = $activity->created_at->format('Y-m-d');
                                $showDateHeader = $lastDate !== $currentDate;
                                $lastDate = $currentDate;
                            @endphp

                            @if($showDateHeader)
                                <div class="date-header mt-4 mb-3">
                                    <h5 class="text-muted">{{ $activity->created_at->format('l, F j, Y') }}</h5>
                                    <hr>
                                </div>
                            @endif

                            <div class="activity-item mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        @if($activity->type === 'project')
                                            {{-- Project Activity --}}
                                            <div class="activity-content">
                                                <strong>{{ $activity->user ? $activity->user->name : 'System' }}</strong>
                                                @if($activity->action === 'created')
                                                    created project
                                                    @if($activity->project)
                                                        <a href="{{ route('projects.show', $activity->project) }}">{{ $activity->project->name }}</a>
                                                    @else
                                                        <span class="text-muted">(deleted project)</span>
                                                    @endif
                                                    @if(!$activity->user && $activity->project)
                                                        <span class="badge bg-secondary ms-2">New</span>
                                                    @endif
                                                @elseif($activity->action === 'updated')
                                                    updated project
                                                    @if($activity->project)
                                                        <a href="{{ route('projects.show', $activity->project) }}">{{ $activity->project->name }}</a>
                                                    @else
                                                        <span class="text-muted">(deleted project)</span>
                                                    @endif
                                                @elseif($activity->action === 'status_changed')
                                                    changed status of
                                                    @if($activity->project)
                                                        <a href="{{ route('projects.show', $activity->project) }}">{{ $activity->project->name }}</a>
                                                    @else
                                                        <span class="text-muted">(deleted project)</span>
                                                    @endif
                                                    from <span class="badge bg-secondary">{{ $activity->metadata['from'] ?? 'unknown' }}</span>
                                                    to <span class="badge bg-primary">{{ $activity->metadata['to'] ?? 'unknown' }}</span>
                                                @else
                                                    {{ $activity->action }} on
                                                    @if($activity->project)
                                                        <a href="{{ route('projects.show', $activity->project) }}">{{ $activity->project->name }}</a>
                                                    @else
                                                        <span class="text-muted">(deleted project)</span>
                                                    @endif
                                                @endif
                                            </div>
                                        @elseif($activity->type === 'task')
                                            {{-- Task Activity --}}
                                            <div class="activity-content">
                                                <strong>{{ $activity->user ? $activity->user->name : 'System' }}</strong>
                                                @if($activity->action === 'status_changed')
                                                    changed task status from
                                                    <span class="badge bg-secondary">{{ $activity->metadata['from'] ?? 'unknown' }}</span>
                                                    to
                                                    <span class="badge bg-primary">{{ $activity->metadata['to'] ?? 'unknown' }}</span>
                                                @elseif($activity->action === 'completed')
                                                    marked task as completed
                                                @elseif($activity->action === 'reopened')
                                                    reopened task
                                                @elseif($activity->action === 'phase_changed')
                                                    moved task to phase: <strong>{{ $activity->metadata['to_name'] ?? 'unknown' }}</strong>
                                                @elseif($activity->action === 'column_changed')
                                                    moved task to column: <strong>{{ $activity->metadata['to_name'] ?? 'unknown' }}</strong>
                                                @elseif($activity->action === 'assignees_changed')
                                                    updated task assignees
                                                @elseif($activity->action === 'due_date_changed')
                                                    changed task due date
                                                @else
                                                    {{ $activity->action }} on task
                                                @endif

                                                @if($activity->task && $activity->task->column && $activity->task->column->project)
                                                    on
                                                    @if($activity->task->phase)
                                                        <a href="{{ route('projects.board', ['project' => $activity->task->column->project, 'phase' => $activity->task->phase->id]) }}">
                                                            {{ $activity->task->column->project->name }}
                                                        </a>
                                                    @else
                                                        <a href="{{ route('projects.board', $activity->task->column->project) }}">
                                                            {{ $activity->task->column->project->name }}
                                                        </a>
                                                    @endif
                                                    <span class="text-muted small">• {{ $activity->task->title }}</span>
                                                @else
                                                    <span class="text-muted">(deleted task)</span>
                                                @endif
                                            </div>
                                        @elseif($activity->type === 'comment')
                                            {{-- Comment Activity --}}
                                            <div class="activity-content">
                                                <strong>{{ $activity->user ? $activity->user->name : 'System' }}</strong>
                                                commented on task
                                                @if($activity->task && $activity->task->column && $activity->task->column->project)
                                                    on
                                                    @if($activity->task->phase)
                                                        <a href="{{ route('projects.board', ['project' => $activity->task->column->project, 'phase' => $activity->task->phase->id]) }}">
                                                            {{ $activity->task->column->project->name }}
                                                        </a>
                                                    @else
                                                        <a href="{{ route('projects.board', $activity->task->column->project) }}">
                                                            {{ $activity->task->column->project->name }}
                                                        </a>
                                                    @endif
                                                    <span class="text-muted small">• {{ $activity->task->title }}</span>
                                                @else
                                                    <span class="text-muted">(deleted task)</span>
                                                @endif
                                                <div class="mt-1 text-muted small fst-italic">
                                                    "{{ \Illuminate\Support\Str::limit($activity->body, 100) }}"
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="text-muted small text-nowrap ms-3">
                                        {{ $activity->created_at->format('g:i A') }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
