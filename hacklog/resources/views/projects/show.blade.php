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
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h3 class="h6 mb-0 fw-semibold">Current Status</h3>
                @if($projectWorkload['weighted_completion_pct'] !== null)
                    <small class="text-muted">{{ $projectWorkload['weighted_completion_pct'] }}% weighted complete</small>
                @endif
            </div>
            <div class="card-body">
                {{-- Weighted completion progress bar --}}
                @if($projectWorkload['has_weight_data'])
                <div class="mb-3">
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-success"
                             role="progressbar"
                             style="width: {{ $projectWorkload['weighted_completion_pct'] ?? 0 }}%"
                             aria-valuenow="{{ $projectWorkload['weighted_completion_pct'] ?? 0 }}"
                             aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">{{ $projectWorkload['completed_weight'] }} pts done</small>
                        <small class="text-muted">{{ $projectWorkload['remaining_weight'] }} pts remaining</small>
                    </div>
                </div>
                @endif

                {{-- Stats row --}}
                <div class="row text-center g-2">
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-2">
                            <div class="fs-5 fw-semibold">{{ $activePhasesCount }}</div>
                            <div class="text-muted small">Active Phases</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-2">
                            <div class="fs-5 fw-semibold @if($overdueTasks > 0) text-danger @endif">{{ $overdueTasks }}</div>
                            <div class="text-muted small @if($overdueTasks > 0) text-danger @endif">Overdue</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-2">
                            <div class="fs-5 fw-semibold @if($projectWorkload['open_high_priority'] > 0) text-danger @endif">
                                {{ $projectWorkload['open_high_priority'] }}
                            </div>
                            <div class="text-muted small">↑ High Priority</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="border rounded p-2">
                            @if($nearestDueDate)
                                <div class="fs-5 fw-semibold">{{ $nearestDueDate->format('M j') }}</div>
                            @else
                                <div class="fs-5 text-muted">—</div>
                            @endif
                            <div class="text-muted small">Next Due</div>
                        </div>
                    </div>
                    @if($projectWorkload['unassigned_high_priority'] > 0)
                    <div class="col-12">
                        <div class="alert alert-warning py-1 px-2 mb-0 d-flex align-items-center gap-2" style="font-size: 0.82rem;">
                            <span>⚠</span>
                            <span>{{ $projectWorkload['unassigned_high_priority'] }} high-priority task{{ $projectWorkload['unassigned_high_priority'] > 1 ? 's' : '' }} with no assignee</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Workload Breakdown --}}
        @if($projectWorkload['has_weight_data'] && ($phaseWorkloads->isNotEmpty() || $projectWorkload['assignee_load']->isNotEmpty()))
        <div class="row g-4 mb-4">
            @if($projectWorkload['assignee_load']->isNotEmpty())
            <div class="col-md-6">
                <h2 class="h5 mb-3">Workload by Assignee</h2>
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Assignee</th>
                                    <th class="text-end">Tasks</th>
                                    <th class="text-end">Load</th>
                                    <th class="text-end pe-3">↑ High</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($projectWorkload['assignee_load'] as $entry)
                                <tr>
                                    <td class="ps-3">{{ $entry['user']->name }}</td>
                                    <td class="text-end text-muted">{{ $entry['task_count'] }}</td>
                                    <td class="text-end fw-semibold">{{ $entry['load'] }}</td>
                                    <td class="text-end pe-3 {{ $entry['high_count'] > 0 ? 'text-danger' : 'text-muted' }}">
                                        {{ $entry['high_count'] ?: '—' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
            @if($phaseWorkloads->isNotEmpty())
            <div class="col-md-6">
                <h2 class="h5 mb-3">Workload by Phase</h2>
                <div class="card">
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0" style="font-size: 0.82rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Phase</th>
                                    <th class="text-end">Remaining</th>
                                    <th class="text-end">Done %</th>
                                    <th class="text-end pe-3">↑ High</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($phaseWorkloads as $pw)
                                <tr>
                                    <td class="ps-3">
                                        @if($pw['phase'])
                                            <a href="{{ route('projects.board', ['project' => $project, 'phase' => $pw['phase']->id]) }}"
                                               class="text-decoration-none text-body">{{ $pw['phase']->name }}</a>
                                        @else
                                            <span class="text-muted fst-italic">No phase</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ $pw['remaining_weight'] }}</td>
                                    <td class="text-end text-muted">
                                        {{ $pw['weighted_completion_pct'] !== null ? $pw['weighted_completion_pct'].'%' : '—' }}
                                    </td>
                                    <td class="text-end pe-3 {{ $pw['open_high_priority'] > 0 ? 'text-danger' : 'text-muted' }}">
                                        {{ $pw['open_high_priority'] ?: '—' }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            @endif
        </div>
        @endif

        {{-- Team Members --}}
        @if($teamMembers->isNotEmpty())
            <h2 class="h4 mb-3">Team Members</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($teamMembers as $member)
                            <span class="badge {{ $member['user']->isClient() ? 'bg-info' : 'bg-secondary' }} text-white px-3 py-2" 
                                  style="font-size: 0.9rem;">
                                {{ $member['user']->name }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

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

                {{-- Recent Activity --}}
                <h2 class="h4 mb-3">Recent Activity</h2>
                <div class="card mb-4">
                    <div class="card-body">
                        @if($recentActivity->isEmpty())
                            <p class="text-muted mb-0">No recent activity to display.</p>
                        @else
                            <div class="list-group list-group-flush">
                                @foreach($recentActivity as $item)
                                    @php
                                        $activity = $item['activity'];
                                        $isTaskActivity = $item['type'] === 'task';
                                    @endphp
                                    <div class="list-group-item px-0 py-2">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                @if($isTaskActivity)
                                                    {{-- Task Activity --}}
                                                    <div class="small mb-1">
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
                                                            moved task to phase: {{ $activity->metadata['to_name'] ?? 'unknown' }}
                                                        @elseif($activity->action === 'assignees_changed')
                                                            updated task assignment
                                                        @elseif($activity->action === 'due_date_changed')
                                                            changed due date to {{ $activity->metadata['to'] ?? 'none' }}
                                                        @elseif($activity->action === 'column_changed')
                                                            moved task to column: {{ $activity->metadata['to_name'] ?? 'unknown' }}
                                                        @elseif($activity->action === 'comment_added')
                                                            added a comment
                                                        @else
                                                            {{ $activity->action }}
                                                        @endif
                                                    </div>
                                                    <div class="small text-muted">
                                                        <a href="{{ route('projects.board', ['project' => $project, 'task' => $activity->task->id]) }}" 
                                                           class="text-decoration-none text-muted">
                                                            {{ $activity->task->title }}
                                                        </a>
                                                        @if($activity->task->phase)
                                                            <span class="mx-1">›</span>
                                                            {{ $activity->task->phase->name }}
                                                        @endif
                                                    </div>
                                                @else
                                                    {{-- Project Activity --}}
                                                    <div class="small mb-1">
                                                        <strong>{{ $activity->user ? $activity->user->name : 'System' }}</strong>
                                                        @if($activity->action === 'created')
                                                            created this project
                                                        @elseif($activity->action === 'updated')
                                                            updated project details
                                                        @elseif($activity->action === 'status_changed')
                                                            changed project status from
                                                            <span class="badge bg-secondary">{{ $activity->metadata['from'] ?? 'unknown' }}</span>
                                                            to
                                                            <span class="badge bg-primary">{{ $activity->metadata['to'] ?? 'unknown' }}</span>
                                                        @else
                                                            {{ $activity->action }}
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="ms-3">
                                                <span class="small text-muted text-nowrap">{{ $activity->created_at->diffForHumans() }}</span>
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
