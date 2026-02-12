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
            @if(Auth::user()->isClient())
                {{-- Projects You're On - Main section for clients --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Your Projects</h2>
                    </div>
                    <div class="card-body">
                        @if($activeProjects->isEmpty())
                            <p class="text-muted mb-0">No projects have been shared with you yet.</p>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($activeProjects as $project)
                                    <div class="list-group-item px-0 py-3">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h5 class="mb-2">
                                                    <a href="{{ route('projects.board', $project) }}" class="text-decoration-none">
                                                        {{ $project->name }}
                                                    </a>
                                                </h5>
                                                @if($project->description)
                                                    <p class="text-muted mb-2 small">{{ Str::limit($project->description, 150) }}</p>
                                                @endif
                                                <div class="d-flex gap-3 flex-wrap">
                                                    <small class="text-muted">{{ $project->user_task_count }} task{{ $project->user_task_count === 1 ? '' : 's' }} assigned to you</small>
                                                    @if($project->next_epic_date)
                                                        <small class="text-muted">
                                                            Next milestone: {{ $project->next_epic_date->format('M j, Y') }}
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
                                            <a href="{{ route('projects.board', $project) }}" class="btn btn-primary">View Board</a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
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
                                            <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ ucfirst($task->status) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ ucfirst($task->status) }}</span>
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
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ ucfirst($task->status) }}</span>
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
                                                <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ ucfirst($task->status) }}</span>
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

            {{-- Recently Active --}}
            @if($recentlyActive->isNotEmpty())
                <div class="card mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">Recently Active</h2>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">Tasks you've worked on in the last 7 days</p>
                        <div class="list-group list-group-flush">
                            @foreach($recentlyActive as $task)
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
                                        <div class="ms-3">
                                            <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">{{ ucfirst($task->status) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
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

            <!-- Projects You're On - Sidebar for non-clients -->
            @if(!Auth::user()->isClient())
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">Projects You're On</h3>
                </div>
                <div class="card-body">
                    @if($activeProjects->isEmpty())
                        <p class="text-muted mb-0 small">No active projects where you're involved.</p>
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
            @endif
            </div>
        </div>
    </div>
@endsection