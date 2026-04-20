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
            {{-- Recent Activity --}}
            @if($recentActivities->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Recent Activity</h2>
                    </div>
                    <div class="card-body">
                        <div class="activity-timeline">
                            @php
                                $lastDate = null;
                            @endphp
                            @foreach($recentActivities as $activity)
                                @php
                                    $currentDate = $activity['activity']->created_at->format('Y-m-d');
                                    $showDateHeader = $lastDate !== $currentDate;
                                    $lastDate = $currentDate;
                                @endphp

                                @if($showDateHeader)
                                    <div class="date-header mt-3 mb-3">
                                        <h6 class="text-muted mb-1">{{ $activity['activity']->created_at->format('l, F j, Y') }}</h6>
                                        <hr class="mt-1">
                                    </div>
                                @endif

                                <div class="activity-item mb-3 pb-3 border-bottom">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            @if($activity['type'] === 'project')
                                                {{-- Project Activity --}}
                                                <div class="activity-content">
                                                    <strong>{{ $activity['activity']->user ? $activity['activity']->user->name : 'System' }}</strong>
                                                    @if($activity['activity']->action === 'created')
                                                        created project
                                                    @elseif($activity['activity']->action === 'updated')
                                                        updated project
                                                    @elseif($activity['activity']->action === 'status_changed')
                                                        changed status of
                                                    @else
                                                        {{ $activity['activity']->action }} on
                                                    @endif
                                                    @if($activity['activity']->project)
                                                        <a href="{{ route('projects.show', $activity['activity']->project) }}">{{ $activity['activity']->project->name }}</a>
                                                    @endif
                                                    @if($activity['activity']->action === 'status_changed' && isset($activity['activity']->metadata['from']) && isset($activity['activity']->metadata['to']))
                                                        from <span class="badge bg-secondary">{{ $activity['activity']->metadata['from'] }}</span>
                                                        to <span class="badge bg-primary">{{ $activity['activity']->metadata['to'] }}</span>
                                                    @endif
                                                </div>
                                            @elseif($activity['type'] === 'task')
                                                {{-- Task Activity --}}
                                                <div class="activity-content">
                                                    <strong>{{ $activity['activity']->user ? $activity['activity']->user->name : 'System' }}</strong>
                                                    @if($activity['activity']->action === 'created')
                                                        created task
                                                    @elseif($activity['activity']->action === 'status_changed')
                                                        changed task status
                                                        @if(isset($activity['activity']->metadata['from']) && isset($activity['activity']->metadata['to']))
                                                            from <span class="badge bg-secondary">{{ $activity['activity']->metadata['from'] }}</span>
                                                            to <span class="badge bg-primary">{{ $activity['activity']->metadata['to'] }}</span>
                                                        @endif
                                                    @elseif($activity['activity']->action === 'completed')
                                                        marked task as completed
                                                    @elseif($activity['activity']->action === 'phase_changed')
                                                        moved task to phase: <strong>{{ $activity['activity']->metadata['to_name'] ?? 'unknown' }}</strong>
                                                    @elseif($activity['activity']->action === 'column_changed')
                                                        moved task to column: <strong>{{ $activity['activity']->metadata['to_name'] ?? 'unknown' }}</strong>
                                                    @elseif($activity['activity']->action === 'assignees_changed')
                                                        updated task assignment
                                                    @else
                                                        {{ $activity['activity']->action }} on task
                                                    @endif
                                                    @if($activity['activity']->task)
                                                        on <a href="{{ route('projects.board', ['project' => $activity['activity']->task->column->project, 'task' => $activity['activity']->task->id]) }}">{{ $activity['activity']->task->column->project->name }}</a>
                                                        <span class="text-muted small">• {{ $activity['activity']->task->title }}</span>
                                                    @endif
                                                </div>
                                            @elseif($activity['type'] === 'comment')
                                                {{-- Comment Activity --}}
                                                <div class="activity-content">
                                                    <strong>{{ $activity['activity']->user ? $activity['activity']->user->name : 'System' }}</strong>
                                                    commented on task
                                                    @if($activity['activity']->task)
                                                        on <a href="{{ route('projects.board', ['project' => $activity['activity']->task->column->project, 'task' => $activity['activity']->task->id]) }}">{{ $activity['activity']->task->column->project->name }}</a>
                                                        <span class="text-muted small">• {{ $activity['activity']->task->title }}</span>
                                                    @endif
                                                    <div class="mt-1 text-muted small fst-italic">
                                                        "{{ Str::limit($activity['activity']->body, 100) }}"
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-muted small text-nowrap ms-2">
                                            {{ $activity['activity']->created_at->format('g:i A') }}
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @elseif(!Auth::user()->isClient())
                {{-- Show message for team/admin users with no favorited projects --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Recent Activity</h2>
                    </div>
                    <div class="card-body text-center py-5">
                        <p class="text-muted mb-3">No recent activity to display.</p>
                        <p class="text-muted">Browse <a href="{{ route('projects.index') }}">projects</a> to add favorites and see activity here.</p>
                    </div>
                </div>
            @endif

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

            {{-- Your Assigned Work - Prioritized Groups (hide for clients with no tasks) --}}
            @if(!Auth::user()->isClient() || $overdueTasks->isNotEmpty() || $dueThisWeek->isNotEmpty() || $dueNext->isNotEmpty() || $noDueDate->isNotEmpty())
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
                                @foreach($dueThisWeek->take(5) as $task)
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
                                @foreach($dueNext->take(5) as $task)
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
                                @foreach($noDueDate->take(5) as $task)
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
            @endif

            {{-- Awaiting Feedback - For non-clients --}}
            @if(!Auth::user()->isClient() && $awaitingFeedbackTasks->isNotEmpty())
                <div class="card mb-4 border-warning">
                    <div class="card-header bg-warning bg-opacity-10">
                        <h2 class="h5 mb-0">Awaiting Feedback</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Tasks waiting for feedback across the organization</p>
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
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            {{-- Projects Section --}}
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">
                        @if(Auth::user()->isClient())
                            My Projects
                        @else
                            My Favorited Projects
                        @endif
                    </h3>
                </div>
                <div class="card-body">
                    @if($activeProjects->isEmpty())
                        @if(Auth::user()->isClient())
                            <p class="text-muted mb-0 small">No projects shared with you yet.</p>
                        @else
                            <p class="text-muted mb-0 small">No favorited projects yet. <a href="{{ route('projects.index') }}">Browse projects</a> to add favorites.</p>
                        @endif
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
                                                @if(Auth::user()->isClient())
                                                    <small class="text-muted">{{ $project->user_task_count }} active task{{ $project->user_task_count === 1 ? '' : 's' }}</small>
                                                    @if(isset($project->feedback_task_count) && $project->feedback_task_count > 0)
                                                        <small class="text-warning">· {{ $project->feedback_task_count }} awaiting feedback</small>
                                                    @endif
                                                @else
                                                    <small class="text-muted">{{ $project->user_task_count }} task{{ $project->user_task_count === 1 ? '' : 's' }}</small>
                                                @endif
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
        </div>
    </div>
@endsection