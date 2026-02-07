@extends('layouts.app')

@section('title', 'Organization Timeline')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1>Organization Timeline</h1>
                <p class="text-muted mb-0">Epic schedules across all projects</p>
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

        @if($epics->isEmpty())
            @if($filterStart || $filterEnd)
                @include('partials.empty-state', [
                    'message' => 'No epics found in the selected date range. Try adjusting your filters or clearing them to see all epics.'
                ])
            @else
                @include('partials.empty-state', [
                    'message' => 'No epics with dates yet. Create epics in your projects and add start or end dates to see them here.',
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
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 120px;" class="bg-light">Project</th>
                                    <th style="width: 150px;" class="bg-light">Epic</th>
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
                                        <th class="text-center bg-light {{ $heatClass }}" style="width: 95px;" title="{{ $dueCount }} {{ Str::plural('due date', $dueCount) }} this week">
                                            <small>{{ $week['label'] }}</small>
                                            @if($dueCount > 0)
                                                <br><a href="{{ route('schedule.index', ['start' => $week['start']->format('Y-m-d'), 'end' => $week['end']->format('Y-m-d')]) }}" class="badge bg-primary rounded-pill text-decoration-none" style="font-size: 0.7rem;">{{ $dueCount }}</a>
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
                                        $projectEpics = $projectGroup['epics'];
                                    @endphp
                                    @foreach($projectEpics as $epic)
                                        @php
                                            // Calculate which weeks this epic spans
                                            $epicStart = $epic->start_date ?: $epic->end_date;
                                            $epicEnd = $epic->end_date ?: $epic->start_date;
                                            
                                            // Determine which week cells to fill
                                            $cellStates = [];
                                            foreach ($weeks as $index => $week) {
                                                $weekEnd = $week['start']->copy()->endOfWeek();
                                                
                                                // Check if epic overlaps this week
                                                if ($epicStart->lte($weekEnd) && $epicEnd->gte($week['start'])) {
                                                    $cellStates[$index] = 'filled';
                                                } else {
                                                    $cellStates[$index] = 'empty';
                                                }
                                            }
                                            
                                            $showProjectName = $lastProjectId !== $project->id;
                                            $lastProjectId = $project->id;
                                        @endphp
                                        <tr class="@if($epic->isOverdue()) border-danger border-2 @endif @if($epic->status === 'completed') bg-light @endif">
                                            <td class="@if($showProjectName) border-top border-dark border-2 @endif @if($epic->status === 'completed') bg-light @endif">
                                                @if($showProjectName)
                                                    <div class="d-flex flex-column">
                                                        <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-decoration-none @if($epic->status === 'completed') text-muted @endif">
                                                            {{ $project->name }}
                                                        </a>
                                                        <small>
                                                            <span class="badge 
                                                                @if($project->status === 'active') bg-success
                                                                @elseif($project->status === 'paused') bg-warning
                                                                @else bg-secondary
                                                                @endif">
                                                                {{ ucfirst($project->status) }}
                                                            </span>
                                                        </small>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="@if($showProjectName) border-top border-dark border-2 @endif @if($epic->isOverdue()) bg-danger-subtle @elseif($epic->status === 'completed') bg-light @endif">
                                                <div class="d-flex flex-column">
                                                    <a href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}" 
                                                       class="@if($epic->isOverdue()) fw-semibold text-danger @elseif($epic->status === 'completed') text-muted @else @endif text-decoration-none">
                                                        {{ $epic->name }}
                                                    </a>
                                                    <small class="text-muted">
                                                        <span class="badge 
                                                            @if($epic->status === 'planned') bg-secondary
                                                            @elseif($epic->status === 'active') bg-success
                                                            @else bg-primary
                                                            @endif">
                                                            {{ ucfirst($epic->status) }}
                                                        </span>
                                                        @if($epic->start_date && $epic->end_date)
                                                            {{ $epic->start_date->format('M j') }} - {{ $epic->end_date->format('M j, Y') }}
                                                        @elseif($epic->start_date)
                                                            Start: {{ $epic->start_date->format('M j, Y') }}
                                                        @else
                                                            End: {{ $epic->end_date->format('M j, Y') }}
                                                        @endif
                                                    </small>
                                                </div>
                                            </td>
                                            @foreach($cellStates as $state)
                                                <td class="@if($state === 'filled') @if($epic->isOverdue()) bg-danger @elseif($epic->status === 'completed') bg-secondary-subtle @else bg-primary @endif @elseif($epic->status === 'completed') bg-light @endif @if($showProjectName) border-top border-dark border-2 @endif" style="width: 95px;">
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
