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
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
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
                                                <br><a href="{{ route('projects.board', $project) }}" class="badge bg-primary rounded-pill text-decoration-none" style="font-size: 0.7rem;">{{ $dueCount }}</a>
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
                                    <tr class="@if($epic->isOverdue()) border-danger border-2 @endif @if($epic->status === 'completed') bg-light @endif">
                                        <td class="@if($epic->isOverdue()) bg-danger-subtle @elseif($epic->status === 'completed') bg-light @endif">
                                            <div class="d-flex flex-column">
                                                <span class="@if($epic->isOverdue()) fw-semibold text-danger @elseif($epic->status === 'completed') text-muted @endif">
                                                    {{ $epic->name }}
                                                </span>
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
                                            <td class="@if($state === 'filled') @if($epic->isOverdue()) bg-danger @elseif($epic->status === 'completed') bg-secondary-subtle @else bg-primary @endif @elseif($epic->status === 'completed') bg-light @endif" style="width: 95px;">
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
