@extends('layouts.app')

@section('title', $project->name . ' - Timeline')

@section('content')
<div class="row">
    <div class="col-12">
        @include('projects.partials.project-nav', ['currentView' => 'timeline'])

        <div class="d-flex justify-content-between align-items-start mb-4">
            <h1 class="mb-0">{{ $project->name }}</h1>
            <div class="d-flex gap-2">
                @if($showCompleted)
                    <a href="{{ route('projects.timeline', $project) }}" class="btn btn-sm btn-outline-secondary">Hide Completed</a>
                @else
                    <a href="{{ route('projects.timeline', ['project' => $project, 'show_completed' => '1']) }}" class="btn btn-sm btn-outline-secondary">Show Completed</a>
                @endif
            </div>
        </div>

        @if($epics->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No epics with dates yet. Create an epic and add start or end dates to visualize your project timeline.',
                'actionUrl' => route('projects.epics.create', $project),
                'actionText' => 'Create an epic'
            ])
        @else
            @if($tooWide)
                <div class="alert alert-warning mb-3">
                    <p class="mb-0">Timeline spans more than 26 weeks. Showing first 26 weeks only.</p>
                </div>
            @endif

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 timeline-table">
                            <thead>
                                <tr>
                                    <th style="width: 150px;" class="timeline-header">Epic</th>
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
                                @foreach($epics as $epic)
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
                                    @endphp
                                    <tr class="timeline-row @if($epic->status === 'completed') opacity-75 @endif">
                                        <td class="timeline-epic-label @if($epic->isOverdue()) timeline-overdue @endif">
                                            <div class="d-flex flex-column gap-1">
                                                <a href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}" 
                                                   class="@if($epic->isOverdue()) text-danger @elseif($epic->status === 'completed') text-muted @else text-body @endif text-decoration-none" style="font-size: 0.875rem;">
                                                    {{ $epic->name }}
                                                </a>
                                                <div class="d-flex flex-column gap-1">
                                                    <div>
                                                        <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.7rem; font-weight: 400;">
                                                            {{ ucfirst($epic->status) }}
                                                        </span>
                                                    </div>
                                                    <div class="text-muted" style="font-size: 0.8125rem; line-height: 1.3;">
                                                        @if($epic->start_date && $epic->end_date)
                                                            {{ $epic->start_date->format('M j') }} – {{ $epic->end_date->format('M j, Y') }}
                                                        @elseif($epic->start_date)
                                                            Start: {{ $epic->start_date->format('M j, Y') }}
                                                        @else
                                                            End: {{ $epic->end_date->format('M j, Y') }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        @foreach($cellStates as $state)
                                            <td class="timeline-cell @if($state === 'filled') @if($epic->isOverdue()) timeline-bar-overdue @elseif($epic->status === 'completed') timeline-bar-completed @else timeline-bar-active @endif @endif" style="width: 95px;">
                                                @if($state === 'filled')
                                                    &nbsp;
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
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
                </small>
            </div>
        @endif
    </div>
</div>
@endsection
