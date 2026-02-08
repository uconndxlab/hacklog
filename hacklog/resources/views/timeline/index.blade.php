@extends('layouts.app')

@section('title', 'Organization Timeline')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1>Organization Timeline</h1>
                <p class="text-muted mb-0">Phase schedules across all projects</p>
            </div>
        </div>

        {{-- Filter Form --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('timeline.index') }}" class="row g-3">
                    <div class="col-md-4">
                        <label for="start" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start" name="start" value="{{ $filterStart }}">
                    </div>
                    <div class="col-md-4">
                        <label for="end" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end" name="end" value="{{ $filterEnd }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        @if($showCompleted)
                            <a href="{{ route('timeline.index', array_filter(['start' => $filterStart, 'end' => $filterEnd])) }}" class="btn btn-outline-secondary">Hide Completed</a>
                        @else
                            <button type="submit" name="show_completed" value="1" class="btn btn-outline-secondary">Show Completed</button>
                        @endif
                    </div>
                    @if($filterStart || $filterEnd)
                        <div class="col-12">
                            <a href="{{ route('timeline.index', $showCompleted ? ['show_completed' => '1'] : []) }}" class="btn btn-sm btn-outline-secondary">Clear Filters</a>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        @if($phases->isEmpty())
            @if($filterStart || $filterEnd)
                @include('partials.empty-state', [
                    'message' => 'No phases found in the selected date range. Try adjusting your filters or clearing them to see all phases.'
                ])
            @else
                @include('partials.empty-state', [
                    'message' => 'No phases with dates yet. Create phases in your projects and add start or end dates to see them here.',
                    'actionUrl' => route('projects.index'),
                    'actionText' => 'View projects'
                ])
            @endif
        @else
            @if($tooWide)
                <div class="alert alert-warning mb-3">
                    <p class="mb-0">Timeline spans more than 26 weeks. Showing first 26 weeks only. Use date filters to narrow the range.</p>
                </div>
            @endif

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 timeline-table">
                            <thead>
                                <tr>
                                    <th style="width: 120px;" class="timeline-header">Project</th>
                                    <th style="width: 150px;" class="timeline-header">Phase</th>
                                    @foreach($weeks as $week)
                                        @php
                                            // Determine heat level based on due date density
                                            $dueCount = $week['due_count'];
                                            $heatClass = '';
                                            if ($dueCount >= 4) {
                                                $heatClass = 'timeline-heat-high';
                                            } elseif ($dueCount >= 2) {
                                                $heatClass = 'timeline-heat-medium';
                                            } elseif ($dueCount >= 1) {
                                                $heatClass = 'timeline-heat-low';
                                            }
                                        @endphp
                                        <th class="text-center timeline-header {{ $heatClass }}" style="width: 95px;" title="{{ $dueCount }} {{ Str::plural('due date', $dueCount) }} this week">
                                            <small class="text-muted">{{ $week['label'] }}</small>
                                            @if($dueCount > 0)
                                                <br><span class="badge bg-secondary bg-opacity-50 rounded-pill" style="font-size: 0.7rem;">{{ $dueCount }}</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $lastProjectId = null;
                                @endphp
                                @foreach($projects as $projectGroup)
                                    @php
                                        $project = $projectGroup['project'];
                                        $projectPhases = $projectGroup['phases'];
                                    @endphp
                                    @foreach($projectPhases as $phase)
                                        @php
                                            // Calculate which weeks this phase spans
                                            $phaseStart = $phase->start_date ?: $phase->end_date;
                                            $phaseEnd = $phase->end_date ?: $phase->start_date;
                                            
                                            // Determine which week cells to fill
                                            $cellStates = [];
                                            foreach ($weeks as $index => $week) {
                                                $weekEnd = $week['start']->copy()->endOfWeek();
                                                
                                                // Check if phase overlaps this week
                                                if ($phaseStart->lte($weekEnd) && $phaseEnd->gte($week['start'])) {
                                                    $cellStates[$index] = 'filled';
                                                } else {
                                                    $cellStates[$index] = 'empty';
                                                }
                                            }
                                            
                                            $showProjectName = $lastProjectId !== $project->id;
                                            $lastProjectId = $project->id;
                                        @endphp
                                        <tr class="@if($phase->isOverdue()) border-danger border-2 @endif @if($phase->status === 'completed') bg-light @endif">
                                            <td class="@if($phase->status === 'completed') bg-light @endif">
                                                @if($showProjectName)
                                                    <div class="d-flex flex-column gap-1">
                                                        <a href="{{ route('projects.show', $project) }}" class="text-decoration-none @if($phase->status === 'completed') text-muted @endif" style="font-size: 0.9375rem;">
                                                            {{ $project->name }}
                                                        </a>
                                                        <div>
                                                            <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.7rem; font-weight: 400;">
                                                                {{ ucfirst($project->status) }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="@if($phase->isOverdue()) bg-danger-subtle @elseif($phase->status === 'completed') bg-light @endif">
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}" 
                                                       class="@if($phase->isOverdue()) text-danger @elseif($phase->status === 'completed') text-muted @else text-body @endif text-decoration-none" style="font-size: 0.875rem;">
                                                        {{ $phase->name }}
                                                    </a>
                                                    <div class="d-flex flex-column gap-1">
                                                        <div>
                                                            <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.7rem; font-weight: 400;">
                                                                {{ ucfirst($phase->status) }}
                                                            </span>
                                                        </div>
                                                        {{-- Task Status Breakdown --}}
                                                        @if($phase->task_counts['planned'] > 0 || $phase->task_counts['active'] > 0 || $phase->task_counts['completed'] > 0)
                                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                                @if($phase->task_counts['planned'] > 0)
                                                                    <small class="badge bg-secondary" style="font-size: 0.65rem;">{{ $phase->task_counts['planned'] }} Planned</small>
                                                                @endif
                                                                @if($phase->task_counts['active'] > 0)
                                                                    <small class="badge bg-success" style="font-size: 0.65rem;">{{ $phase->task_counts['active'] }} Active</small>
                                                                @endif
                                                                @if($phase->task_counts['completed'] > 0)
                                                                    <small class="badge bg-light text-dark" style="font-size: 0.65rem;">{{ $phase->task_counts['completed'] }} Completed</small>
                                                                @endif
                                                            </div>
                                                        @endif
                                                        <div class="text-muted" style="font-size: 0.8125rem; line-height: 1.3;">
                                                            @if($phase->start_date && $phase->end_date)
                                                                {{ $phase->start_date->format('M j') }} – {{ $phase->end_date->format('M j, Y') }}
                                                            @elseif($phase->start_date)
                                                                Start: {{ $phase->start_date->format('M j, Y') }}
                                                            @else
                                                                End: {{ $phase->end_date->format('M j, Y') }}
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            @foreach($cellStates as $state)
                                                <td class="timeline-cell @if($state === 'filled') @if($phase->isOverdue()) timeline-bar-overdue @elseif($phase->status === 'completed') timeline-bar-completed @else timeline-bar-active @endif @endif" style="width: 95px;">
                                                    @if($state === 'filled')
                                                        &nbsp;
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <small class="text-muted">
                    Timeline shows weeks from {{ $timelineStart->format('M j, Y') }} to {{ $timelineEnd->format('M j, Y') }}
                    @if($tooWide)
                        (first 26 weeks)
                    @endif
                    @if($filterStart || $filterEnd)
                        — filtered view
                    @endif
                </small>
            </div>
        @endif
    </div>
</div>
@endsection
