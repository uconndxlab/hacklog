@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1>Dashboard</h1>
                    <p class="text-muted mb-0">Here's what you've got on your plate.</p>
                </div>
                @if(Auth::user()->isClient())
                    <span class="badge bg-info" style="font-size: 0.875rem;">Client Access</span>
                @endif
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-lg-8">
            {{-- Awaiting Feedback - Priority for Clients --}}
            @if(Auth::user()->isClient() && $awaitingFeedbackTasks->isNotEmpty())
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h2 class="h5 mb-0">Awaiting Your Feedback</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Tasks that need your review or input</p>
                        <div class="list-group list-group-flush">
                            @foreach($awaitingFeedbackTasks as $task)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-decoration-none">
                                                    {{ $task->title }}
                                                </a>
                                            </h6>
                                            <p class="mb-1 text-muted small">
                                                <a href="{{ route('projects.board', $task->getProject()) }}" class="text-decoration-none text-muted">{{ $task->getProject()->name }}</a>
                                                @if($task->phase)
                                                     › {{ $task->phase->name }}
                                                @endif
                                            </p>
                                            <small class="text-muted">Updated {{ $task->updated_at->diffForHumans() }}</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Needs Attention Section (conditional) --}}
            @if($overdueTasks->isNotEmpty())
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h2 class="h5 mb-0">Needs Attention</h2>
                    </div>
                    <div class="card-body">
                        <div class="list-group list-group-flush">
                            @foreach($overdueTasks as $task)
                                @php
                                    $effectiveDueDate = $task->getEffectiveDueDate();
                                    $isInherited = !$task->due_date && $effectiveDueDate;
                                @endphp
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-decoration-none">
                                                    {{ $task->title }}
                                                </a>
                                            </h6>
                                            <p class="mb-1 text-muted small">
                                                <a href="{{ route('projects.board', $task->getProject()) }}" class="text-decoration-none text-muted">{{ $task->getProject()->name }}</a>
                                                @if($task->phase)
                                                     › {{ $task->phase->name }}
                                                @endif
                                            </p>
                                            <span class="badge bg-danger">
                                                Overdue: {{ $effectiveDueDate->format('M j, Y') }}
                                                @if($isInherited)
                                                    (from phase)
                                                @endif
                                            </span>
                                        </div>
                                        <div class="ms-3">
                                            <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ $task->status_display }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Awaiting Feedback - For non-clients --}}
            @if(!Auth::user()->isClient() && $awaitingFeedbackTasks->isNotEmpty())
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h2 class="h5 mb-0">Awaiting Feedback</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Tasks waiting for client feedback across the organization</p>
                        <div class="list-group list-group-flush">
                            @foreach($awaitingFeedbackTasks->take(8) as $task)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-decoration-none">
                                                    {{ $task->title }}
                                                </a>
                                            </h6>
                                            <p class="mb-1 text-muted small">
                                                <a href="{{ route('projects.board', $task->getProject()) }}" class="text-decoration-none text-muted">{{ $task->getProject()->name }}</a>
                                                @if($task->phase)
                                                     › {{ $task->phase->name }}
                                                @endif
                                            </p>
                                            @if($task->users->isNotEmpty())
                                                <small class="text-muted">
                                                    Assigned to: {{ $task->users->pluck('name')->join(', ') }}
                                                </small>
                                            @endif
                                        </div>
                                        <div class="text-muted small text-nowrap ms-3">
                                            {{ $task->updated_at->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($awaitingFeedbackTasks->count() > 8)
                            <div class="mt-2 text-center">
                                <small class="text-muted">Showing 8 of {{ $awaitingFeedbackTasks->count() }}</small>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Your Assigned Work - Prioritized Groups --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Your Assigned Work</h2>
                </div>
                <div class="card-body">
                    {{-- Due This Week --}}
                    @if($dueThisWeek->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 text-muted mb-3">Due this week</h3>
                            <div class="list-group list-group-flush">
                                @foreach($dueThisWeek as $task)
                                    @php
                                        $effectiveDueDate = $task->getEffectiveDueDate();
                                        $isInherited = !$task->due_date && $effectiveDueDate;
                                    @endphp
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-decoration-none">
                                                        {{ $task->title }}
                                                    </a>
                                                </h6>
                                                <p class="mb-1 text-muted small">
                                                   
                                                   <a href="{{ route('projects.board', $task->getProject()) }}" class="text-decoration-none text-muted">{{ $task->getProject()->name }}</a>
                                                    
                                                   @if($task->phase)
                                                         › {{ $task->phase->name }}
                                                    @endif
                                                </p>
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">
                                                    Due {{ $effectiveDueDate->format('M j') }}
                                                    @if($isInherited)
                                                        (from phase)
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="ms-3">
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ $task->status_display }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="mb-4">
                            <h3 class="h6 text-muted mb-3">Due this week</h3>
                            <p class="text-muted mb-0 small">No tasks due this week</p>
                        </div>
                    @endif

                    {{-- Due Next --}}
                    @if($dueNext->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="h6 text-muted mb-3">Coming up</h3>
                            <div class="list-group list-group-flush">
                                @foreach($dueNext as $task)
                                    @php
                                        $effectiveDueDate = $task->getEffectiveDueDate();
                                        $isInherited = !$task->due_date && $effectiveDueDate;
                                    @endphp
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-decoration-none">
                                                        {{ $task->title }}
                                                    </a>
                                                </h6>
                                                <p class="mb-1 text-muted small">

                                                    <a href="{{ route('projects.board', $task->getProject()) }}" class="text-decoration-none text-muted">{{ $task->getProject()->name }}</a>

                                                    @if($task->phase)
                                                         › {{ $task->phase->name }}
                                                    @endif
                                                </p>
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">
                                                    Due {{ $effectiveDueDate->format('M j, Y') }}
                                                    @if($isInherited)
                                                        (from phase)
                                                    @endif
                                                </span>
                                            </div>
                                            <div class="ms-3">
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ $task->status_display }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- No Due Date --}}
                    @if($noDueDate->isNotEmpty())
                        <div>
                            <h3 class="h6 text-muted mb-3">No due date</h3>
                            <div class="list-group list-group-flush">
                                @foreach($noDueDate as $task)
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-decoration-none">
                                                        {{ $task->title }}
                                                    </a>
                                                </h6>
                                                <p class="mb-1 text-muted small">
                                                    {{ $task->getProject()->name }}
                                                    @if($task->phase)
                                                         › {{ $task->phase->name }}
                                                    @endif
                                                </p>
                                            </div>
                                            <div class="ms-3">
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ $task->status_display }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Empty State --}}
                    @if($dueThisWeek->isEmpty() && $dueNext->isEmpty() && $noDueDate->isEmpty())
                        <p class="text-muted mb-0">You don't have any tasks assigned to you right now. Tasks will appear here once you're assigned to them.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            {{-- Projects You're On --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">My Favorited Projects</h3>
                </div>
                <div class="card-body">
                    @if($activeProjects->isEmpty())
                        <p class="text-muted mb-0 small">No favorited projects yet. <a href="{{ route('projects.index') }}">Browse projects</a> to add favorites.</p>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($activeProjects as $project)
                                <div class="list-group-item px-0 py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-2">
                                                <a href="{{ route('projects.board', $project) }}" class="text-decoration-none">
                                                    {{ $project->name }}
                                                </a>
                                            </h6>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <small class="text-muted">{{ $project->user_task_count }} task{{ $project->user_task_count === 1 ? '' : 's' }}</small>
                                                @if($project->next_epic_date)
                                                    <small class="text-muted">
                                                        @if($project->next_epic_date->isPast())
                                                            <span class="text-danger">· Overdue</span>
                                                        @elseif($project->next_epic_date->isToday())
                                                            <span class="text-warning">· Due today</span>
                                                        @elseif($project->next_epic_date->diffInDays() <= 7)
                                                            <span class="text-warning">· Due soon</span>
                                                        @endif
                                                    </small>
                                                @endif
                                            </div>
                                        </div>
                                        <a href="{{ route('projects.board', $project) }}" class="btn btn-sm btn-outline-primary">View</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Unassigned Tasks Section - Hidden for clients --}}
            @if(!Auth::user()->isClient() && $unassignedTasks->isNotEmpty())
                <div class="card mb-4 border-info">
                    <div class="card-header bg-info bg-opacity-10">
                        <h3 class="h5 mb-0">Tasks Without Anyone Assigned</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Available tasks that need someone to work on them</p>
                        <div class="list-group list-group-flush">
                            @foreach($unassignedTasks as $task)
                                @php
                                    $effectiveDueDate = $task->getEffectiveDueDate();
                                    $isInherited = !$task->due_date && $effectiveDueDate;
                                @endphp
                                <div class="list-group-item px-0 py-2">
                                    <div class="d-flex flex-column">
                                        <h6 class="mb-1">
                                            <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}" class="text-decoration-none">
                                                {{ $task->title }}
                                            </a>
                                        </h6>
                                        <small class="text-muted mb-1">
                                            <a href="{{ route('projects.board', $task->getProject()) }}" class="text-decoration-none text-muted">{{ $task->getProject()->name }}</a>

                                        </small>
                                        @if($effectiveDueDate)
                                            <small class="text-muted">
                                                Due {{ $effectiveDueDate->format('M j') }}
                                                @if($isInherited)
                                                    (from phase)
                                                @endif
                                            </small>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
            
            {{-- Recent Activity --}}
            @if($recentActivities->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="h5 mb-0">Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Latest updates from the last 7 days</p>
                        <div class="activity-timeline">
                            @foreach($recentActivities->take(8) as $item)
                                <div class="activity-item mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            @if($item->type === 'project')
                                                {{-- Project Activity --}}
                                                <div class="activity-content small">
                                                    <strong>{{ $item->activity->user ? $item->activity->user->name : 'System' }}</strong>
                                                    @if($item->activity->action === 'created')
                                                        created project
                                                        @if($item->activity->project)
                                                            <a href="{{ route('projects.show', $item->activity->project) }}">{{ $item->activity->project->name }}</a>
                                                        @endif
                                                    @elseif($item->activity->action === 'updated')
                                                        updated
                                                        @if($item->activity->project)
                                                            <a href="{{ route('projects.show', $item->activity->project) }}">{{ $item->activity->project->name }}</a>
                                                        @endif
                                                    @elseif($item->activity->action === 'status_changed')
                                                        changed status of
                                                        @if($item->activity->project)
                                                            <a href="{{ route('projects.show', $item->activity->project) }}">{{ $item->activity->project->name }}</a>
                                                        @endif
                                                    @else
                                                        {{ $item->activity->action }} on
                                                        @if($item->activity->project)
                                                            <a href="{{ route('projects.show', $item->activity->project) }}">{{ $item->activity->project->name }}</a>
                                                        @endif
                                                    @endif
                                                </div>
                                            @elseif($item->type === 'task')
                                                {{-- Task Activity --}}
                                                <div class="activity-content small">
                                                    <strong>{{ $item->activity->user ? $item->activity->user->name : 'System' }}</strong>
                                                    @if($item->activity->action === 'status_changed')
                                                        changed task status
                                                    @elseif($item->activity->action === 'completed')
                                                        completed task
                                                    @elseif($item->activity->action === 'phase_changed')
                                                        moved task
                                                    @elseif($item->activity->action === 'column_changed')
                                                        moved task
                                                    @elseif($item->activity->action === 'assignees_changed')
                                                        updated assignment
                                                    @else
                                                        {{ $item->activity->action }}
                                                    @endif
                                                    @if($item->activity->task && $item->activity->task->column && $item->activity->task->column->project)
                                                        on
                                                        <a href="{{ route('projects.board', ['project' => $item->activity->task->column->project, 'task' => $item->activity->task->id]) }}">
                                                            {{ $item->activity->task->column->project->name }}
                                                        </a>
                                                    @endif
                                                </div>
                                            @elseif($item->type === 'comment')
                                                {{-- Comment Activity --}}
                                                <div class="activity-content small">
                                                    <strong>{{ $item->activity->user ? $item->activity->user->name : 'System' }}</strong>
                                                    commented
                                                    @if($item->activity->task && $item->activity->task->column && $item->activity->task->column->project)
                                                        on
                                                        <a href="{{ route('projects.board', ['project' => $item->activity->task->column->project, 'task' => $item->activity->task->id]) }}">
                                                            {{ $item->activity->task->column->project->name }}
                                                        </a>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-muted small text-nowrap ms-2" style="font-size: 0.75rem;">
                                            {{ $item->activity->created_at->diffForHumans(null, true, true) }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($recentActivities->count() > 8)
                            <div class="mt-2 text-center">
                                <small class="text-muted">Showing 8 recent items</small>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection