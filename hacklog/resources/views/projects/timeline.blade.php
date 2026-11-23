@extends('layouts.app')

@section('title', $project->name . ' - Timeline')

@section('content')
<div class="row">
    <div class="col-12">
        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'timeline'])

        {{-- Filter Form --}}
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('projects.timeline', $project) }}" class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label for="start" class="form-label mb-0 text-nowrap">From</label>
                        <input type="date" class="form-control form-control-sm" id="start" name="start" value="{{ $filterStart }}" onchange="this.form.submit()">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="end" class="form-label mb-0 text-nowrap">To</label>
                        <input type="date" class="form-control form-control-sm" id="end" name="end" value="{{ $filterEnd }}" onchange="this.form.submit()">
                    </div>
                    @if($showCompleted)
                        <a href="{{ route('projects.timeline', array_merge(['project' => $project], array_filter(['start' => $filterStart, 'end' => $filterEnd]))) }}" class="btn btn-sm btn-outline-secondary">Hide Completed</a>
                    @else
                        <a href="{{ route('projects.timeline', array_merge(['project' => $project], array_filter(['start' => $filterStart, 'end' => $filterEnd, 'show_completed' => '1']))) }}" class="btn btn-sm btn-outline-secondary">Show Completed</a>
                    @endif
                    <a href="{{ route('projects.timeline', $project) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <button type="button" class="btn btn-sm btn-primary"
                            hx-get="{{ route('projects.phases.create', $project) }}"
                            hx-target="#phaseCreateModalContent"
                            hx-swap="innerHTML"
                            onclick="bootstrap.Modal.getOrCreateInstance(document.getElementById('phaseCreateModal')).show()">
                        Add Phase
                    </button>
                </form>
            </div>
        </div>

        @if($phases->isEmpty())
            <div class="alert alert-light border">
                <p class="mb-0 text-muted">No phases with dates yet. Add a phase with start or end dates to visualize your project timeline.</p>
            </div>
        @else
            @if($tooWide)
                <div class="alert alert-warning mb-3">
                    <p class="mb-0">Timeline spans more than 26 weeks. Showing first 26 weeks only.</p>
                </div>
            @endif

            @php
                $visibleEnd = $timelineStart->copy()->addDays($timelineDayCount - 1);
                $weekCountVisible = count($weeks);
                $gridColumns = '240px minmax(850px, 1fr)';
            @endphp
            <div class="card">
                <div class="card-body p-0">
                    <div class="gantt-scroll" style="overflow-x: auto;">
                        <div class="gantt-chart">
                            <div class="gantt-row gantt-header border-bottom" style="grid-template-columns: {{ $gridColumns }};">
                                <div class="px-3 py-2 fw-semibold border-end">Phase / Task</div>
                                <div class="gantt-scale" style="grid-template-columns: repeat({{ $weekCountVisible }}, minmax(0, 1fr));">
                                    @foreach($weeks as $week)
                                        <div class="text-center py-2 border-end small text-muted">{{ $week['label'] }}</div>
                                    @endforeach
                                </div>
                            </div>

                            @foreach($phases as $phase)
                                @php
                                    $phaseStart = $phase->start_date ?? $phase->end_date;
                                    $phaseEnd = $phase->end_date ?? $phase->start_date;
                                    $phaseVisible = ! $phaseEnd->lt($timelineStart) && ! $phaseStart->gt($visibleEnd);
                                    $clippedPhaseStart = $phaseStart->lt($timelineStart) ? $timelineStart : $phaseStart;
                                    $clippedPhaseEnd = $phaseEnd->gt($visibleEnd) ? $visibleEnd : $phaseEnd;
                                    $phaseStartOffset = $phaseVisible ? $timelineStart->diffInDays($clippedPhaseStart) : 0;
                                    $phaseEndOffset = $phaseVisible ? $timelineStart->diffInDays($clippedPhaseEnd) : 0;
                                    $phaseBarLeft = $timelineDayCount ? ($phaseStartOffset / $timelineDayCount * 100) : 0;
                                    $phaseBarWidth = $timelineDayCount ? (($phaseEndOffset - $phaseStartOffset + 1) / $timelineDayCount * 100) : 0;
                                    $phaseDateLabel = $phase->start_date && $phase->end_date
                                        ? $phase->start_date->format('M j') . ' – ' . $phase->end_date->format('M j, Y')
                                        : ($phase->start_date ? 'Starts ' . $phase->start_date->format('M j, Y') : 'Ends ' . $phase->end_date->format('M j, Y'));
                                    $datedTasks = $phase->tasks->filter(function ($task) use ($timelineStart, $visibleEnd) {
                                        if (! $task->start_date && ! $task->due_date) return false;
                                        $taskStart = $task->start_date ?? $task->due_date;
                                        $taskEnd = $task->due_date ?? $task->start_date;
                                        return ! $taskEnd->lt($timelineStart) && ! $taskStart->gt($visibleEnd);
                                    })->values();
                                    $phaseRowHeight = 60 + ($datedTasks->count() * 23);
                                @endphp
                                <div class="gantt-row gantt-phase-row border-bottom" style="grid-template-columns: {{ $gridColumns }}; min-height: {{ $phaseRowHeight }}px;">
                                    <div class="gantt-label px-3 py-2 border-end">
                                        <div class="d-flex align-items-center gap-2">
                                            <a class="text-body text-decoration-none fw-semibold text-truncate" href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}" title="{{ $phase->name }}">{{ $phase->name }}</a>
                                            <span class="badge gantt-status border-0 fw-normal flex-shrink-0">{{ ucfirst($phase->status) }}</span>
                                        </div>
                                        <small class="text-muted">{{ $phaseDateLabel }}</small>
                                    </div>
                                    <div class="gantt-track">
                                        <div class="gantt-weeks" style="grid-template-columns: repeat({{ $weekCountVisible }}, minmax(0, 1fr));">
                                            @foreach($weeks as $week)<div></div>@endforeach
                                        </div>
                                        @if($phaseVisible)
                                            <div class="gantt-bar gantt-phase-bar @if($phase->isOverdue()) gantt-phase-overdue @elseif($phase->status === 'completed') gantt-phase-completed @else gantt-phase-active @endif" style="left: {{ $phaseBarLeft }}%; width: {{ $phaseBarWidth }}%; top: 10px;" title="{{ $phase->name }} · {{ $phaseDateLabel }}"></div>
                                        @endif
                                        @foreach($datedTasks as $task)
                                            @php
                                                $taskStart = $task->start_date ?? $task->due_date;
                                                $taskEnd = $task->due_date ?? $task->start_date;
                                                $clippedTaskStart = $taskStart->lt($timelineStart) ? $timelineStart : $taskStart;
                                                $clippedTaskEnd = $taskEnd->gt($visibleEnd) ? $visibleEnd : $taskEnd;
                                                $taskStartOffset = $timelineStart->diffInDays($clippedTaskStart);
                                                $taskEndOffset = $timelineStart->diffInDays($clippedTaskEnd);
                                                $taskBarLeft = $taskStartOffset / $timelineDayCount * 100;
                                                $taskBarWidth = ($taskEndOffset - $taskStartOffset + 1) / $timelineDayCount * 100;
                                                $taskDateLabel = $task->start_date && $task->due_date
                                                    ? $task->start_date->format('M j') . ' – ' . $task->due_date->format('M j, Y')
                                                    : ($task->start_date ? 'Starts ' . $task->start_date->format('M j, Y') : 'Due ' . $task->due_date->format('M j, Y'));
                                                $taskBarClass = match ($task->status) {
                                                    'completed' => 'gantt-task-completed',
                                                    'active' => 'gantt-task-active',
                                                    default => 'gantt-task-planned',
                                                };
                                            @endphp
                                            <div class="gantt-bar gantt-task-bar {{ $taskBarClass }}" style="left: {{ $taskBarLeft }}%; width: {{ $taskBarWidth }}%; top: {{ 34 + ($loop->index * 23) }}px;" title="{{ $task->title }} · {{ $taskDateLabel }}">
                                                <span>{{ $task->title }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
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
                <div class="gantt-legend d-flex gap-3 mt-2 small flex-wrap">
                    <span><span class="d-inline-block rounded-pill me-1 gantt-legend-phase"></span>Phase</span>
                    <span><span class="d-inline-block rounded-pill me-1 gantt-legend-active"></span>Active task</span>
                    <span><span class="d-inline-block rounded-pill me-1 gantt-legend-planned"></span>Planned task</span>
                    <span><span class="d-inline-block rounded-pill me-1 gantt-legend-completed"></span>Completed task</span>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
