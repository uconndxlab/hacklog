@extends('layouts.app')

@section('title', 'Organization Schedule')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="mb-1">Organization Schedule</h1>
                <p class="text-muted mb-0">Upcoming work across all projects</p>
            </div>
        </div>

        {{-- Filter Form --}}
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('schedule.index') }}" class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label for="start" class="form-label mb-0 text-nowrap">From</label>
                        <input type="date" class="form-control form-control-sm" id="start" name="start"
                            value="{{ $filterStart->format('Y-m-d') }}" onchange="this.form.submit()">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="end" class="form-label mb-0 text-nowrap">To</label>
                        <input type="date" class="form-control form-control-sm" id="end" name="end"
                            value="{{ $filterEnd->format('Y-m-d') }}" onchange="this.form.submit()">
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="project_id" class="form-label mb-0 text-nowrap">Project</label>
                        <select class="form-select form-select-sm" id="project_id" name="project_id" onchange="this.form.submit()">
                            <option value="">All</option>
                            @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ request('project_id')==$project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="assignee" class="form-label mb-0 text-nowrap">Assignee</label>
                        <select class="form-select form-select-sm" id="assignee" name="assignee" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="unassigned" {{ request('assignee')==='unassigned' ? 'selected' : '' }}>Unassigned</option>
                            @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('assignee')==$user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <a href="{{ route('schedule.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </form>
            </div>
        </div>

        {{-- Summary Statistics and Charts --}}
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="h5 mb-3">Summary</h2>
                @if($activeProject || $activeAssignee)
                <p class="text-muted small mb-3">
                    @if($activeProject) Filtered by project: <strong>{{ $activeProject->name }}</strong> @endif
                    @if($activeProject && $activeAssignee) • @endif
                    @if($activeAssignee) Filtered by assignee: <strong>{{ is_string($activeAssignee) ? $activeAssignee :
                        $activeAssignee->name }}</strong> @endif
                </p>
                @endif
                <div class="row">
                    @if(!request('assignee') && !request('project_id'))
                    <div class="col-md-6">

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="h6 card-title">Most Tasks</h3>
                                @if($busiestAssignees->isNotEmpty())
                                <div class="list-group list-group-flush">
                                    @foreach($busiestAssignees->take(5) as $assignee)
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>{{ $assignee->name }}</span>
                                            <span class="badge bg-primary">{{ $assignee->tasks_count }} tasks</span>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                                @else
                                <p class="text-muted small mb-0">No assignees found.</p>
                                @endif
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h3 class="h6 card-title">No Tasks</h3>
                                @if($usersWithoutTasks->isNotEmpty())
                                <div class="list-group list-group-flush">
                                    @foreach($usersWithoutTasks->take(5) as $user)
                                    <div class="list-group-item px-0">
                                        {{ $user->name }}
                                    </div>
                                    @endforeach
                                    @if($usersWithoutTasks->count() > 5)
                                    <div class="list-group-item px-0">
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#usersWithoutTasksModal">
                                            View All ({{ $usersWithoutTasks->count() }})
                                        </button>
                                    </div>
                                    @endif
                                </div>
                                @else
                                <p class="text-muted small mb-0">All users have tasks assigned.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="h6 card-title">Due-Date Pressure</h3>
                                <div id="dueDateChart" style="height: 200px;"></div>
                            </div>
                        </div>
                    </div>
                    @if($chartData)
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h3 class="h6 card-title">
                                    @if(request('assignee')) Tasks by Project @else Tasks by Assignee @endif
                                </h3>
                                <div id="distributionChart" style="height: 200px;"></div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3">
                                        <div class="h4 mb-1">{{ $totalTasks }}</div>
                                        <div class="small text-muted">Total Tasks</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="h4 mb-1">{{ $overdueCount }}</div>
                                        <div class="small text-muted">Overdue</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="h4 mb-1">{{ $unassignedCount }}</div>
                                        <div class="small text-muted">Unassigned</div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="h4 mb-1">{{ $distinctProjects }}</div>
                                        <div class="small text-muted">Projects</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Overdue Section --}}
        @if($overdueGrouped->isNotEmpty())
        <div class="mb-4">
            <h2 class="h4 mb-3 text-danger fw-semibold">Overdue</h2>
            @foreach($overdueGrouped as $date => $tasks)
            <div class="card mb-3 border-danger">
                <div class="card-header bg-danger bg-opacity-10">
                    <h3 class="h6 mb-0 text-danger fw-semibold">
                        {{ Carbon\Carbon::parse($date)->format('l, F j, Y') }}
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($tasks as $task)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h4 class="h6 mb-1 text-danger fw-semibold">
                                        @if($task->phase)
                                        <a href="{{ route('projects.phases.tasks.show', [$task->phase->project, $task->phase, $task]) }}"
                                            class="schedule-task-title text-danger text-decoration-none">
                                            {{ $task->title }}
                                        </a>
                                        @else
                                        <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}"
                                            class="schedule-task-title text-danger text-decoration-none">
                                            {{ $task->title }}
                                        </a>
                                        @endif
                                    </h4>
                                    <div class="small text-muted">
                                        <span class="fw-medium">{{ $task->getProject()->name }}</span>
                                        
                                        @if($task->phase)
                                        <span class="mx-1">→</span>
                                        <span class="schedule-phase-name">{{ $task->phase->name }}</span>
                                        @endif
                                        <span class="mx-1">•</span>
                                        <span>{{ $task->column->name }}</span>
                                        @if($task->users->isNotEmpty())
                                        <span class="mx-1">•</span>
                                        <span>{{ $task->users->pluck('name')->join(', ') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="ms-3 text-end">
                                    <div class="small text-danger fw-medium">
                                        {{ Carbon\Carbon::parse($date)->diffForHumans() }}
                                    </div>
                                    <span class="badge bg-danger">Overdue</span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Upcoming Section --}}
        @if($rangeGrouped->isNotEmpty())
        <div class="mb-4">
            <h2 class="h4 mb-3">Upcoming</h2>
            @foreach($rangeGrouped as $date => $tasks)
            @php
            $dateObj = Carbon\Carbon::parse($date);
            $isToday = $dateObj->isToday();
            $isTomorrow = $dateObj->isTomorrow();
            @endphp
            <div class="card mb-3 @if($isToday) border-primary @endif">
                <div class="card-header @if($isToday) bg-primary bg-opacity-10 @else bg-light @endif">
                    <h3 class="h6 mb-0 @if($isToday) text-primary @endif fw-semibold">
                        {{ $dateObj->format('l, F j, Y') }}
                        @if($isToday)
                        <span class="badge bg-primary ms-2">Today</span>
                        @elseif($isTomorrow)
                        <span class="badge bg-secondary ms-2">Tomorrow</span>
                        @endif
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($tasks as $task)
                        <div class="list-group-item @if($task->status === 'completed') bg-light @endif">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h4 class="h6 mb-1 @if($task->status === 'completed') text-muted @endif">
                                        @if($task->phase)
                                        <a href="{{ route('projects.phases.tasks.show', [$task->phase->project, $task->phase, $task]) }}"
                                            class="schedule-task-title @if($task->status === 'completed') text-muted @else text-body @endif text-decoration-none">
                                            {{ $task->title }}
                                        </a>
                                        @else
                                        <a href="{{ route('projects.board', ['project' => $task->getProject(), 'task' => $task->id]) }}"
                                            class="schedule-task-title @if($task->status === 'completed') text-muted @else text-body @endif text-decoration-none">
                                            {{ $task->title }}
                                        </a>
                                        @endif
                                    </h4>
                                    <div
                                        class="small @if($task->status === 'completed') text-muted @else text-muted @endif">
                                        <span class="fw-medium">{{ $task->getProject()->name }}</span>
                                        @if($task->phase)
                                        <span class="mx-1">→</span>
                                        <span class="schedule-phase-name">{{ $task->phase->name }}</span>
                                        @endif
                                        <span class="mx-1">•</span>
                                        <span>{{ $task->column->name }}</span>
                                        @if($task->users->isNotEmpty())
                                        <span class="mx-1">•</span>
                                        <span>{{ $task->users->pluck('name')->join(', ') }}</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="ms-3 text-end">
                                    <span class="badge 
                                                    @if($task->status === 'planned') bg-secondary
                                                    @elseif($task->status === 'active') bg-success
                                                    @else bg-primary
                                                    @endif">
                                        {{ ucfirst($task->status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Empty State --}}
        @if($overdueGrouped->isEmpty() && $rangeGrouped->isEmpty())
        @include('partials.empty-state', [
        'message' => 'No tasks found in the selected date range. Tasks with due dates will appear here.',
        ])
        @endif
    </div>
</div>

<!-- Modal for Users Without Tasks -->
<div class="modal fade" id="usersWithoutTasksModal" tabindex="-1" aria-labelledby="usersWithoutTasksModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usersWithoutTasksModalLabel">Users Without Tasks ({{
                    $usersWithoutTasks->count() }})</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @if($usersWithoutTasks->isNotEmpty())
                <div class="list-group">
                    @foreach($usersWithoutTasks as $user)
                    <div class="list-group-item">
                        {{ $user->name }}
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-muted mb-0">All users have tasks assigned.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<script src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawCharts);

    window.addEventListener('hacklog-theme-change', function() {
        if (document.getElementById('dueDateChart')) drawCharts();
    });

function drawCharts() {
    // Chart colors for light and dark theme 
    var isDark = document.body.classList.contains('theme-dark');
    var chartBg = isDark ? '#232a36' : 'white';
    var textColor = isDark ? '#b8bcc6' : '#333';
    var annotationColor = isDark ? '#e2e6eb' : '#000';

    // Due Date Chart
    var dueData = google.visualization.arrayToDataTable([
        ['Bucket', 'Count', {role: 'annotation'}],
        ['Overdue', {{ $dueDateBuckets['overdue'] }}, '{{ $dueDateBuckets['overdue'] }}'],
        ['Next 7 days', {{ $dueDateBuckets['next7'] }}, '{{ $dueDateBuckets['next7'] }}'],
        ['Next 14 days', {{ $dueDateBuckets['next14'] }}, '{{ $dueDateBuckets['next14'] }}'],
        ['Later', {{ $dueDateBuckets['later'] }}, '{{ $dueDateBuckets['later'] }}']
    ]);
    var dueOptions = {
        backgroundColor: chartBg,
        chartArea: {width: '80%', height: '70%'},
        legend: {position: 'bottom', textStyle: {color: textColor}},
        bars: 'horizontal',
        colors: ['#dc3545', '#fd7e14', '#ffc107', '#6c757d'],
        hAxis: {textStyle: {color: textColor}, baselineColor: isDark ? '#3d4553' : '#ccc'},
        vAxis: {textStyle: {color: textColor}, baselineColor: isDark ? '#3d4553' : '#ccc'},
        annotations: {
            alwaysOutside: true,
            textStyle: { 
                fontSize: 12, 
                color: annotationColor 
            }
        }
    };
    var dueChart = new google.visualization.BarChart(document.getElementById('dueDateChart'));
    dueChart.draw(dueData, dueOptions);

    @if($chartData)
    // Distribution Chart
    var distData = google.visualization.arrayToDataTable([
        ['Category', 'Count', {role: 'annotation'}],
        @foreach($chartData as $key => $value)
        ['{{ addslashes($key) }}', {{ $value }}, '{{ $value }}'],
        @endforeach
    ]);
    var distOptions = {
        backgroundColor: chartBg,
        chartArea: {width: '80%', height: '70%'},
        legend: {position: 'bottom', textStyle: {color: textColor}},
        hAxis: {textStyle: {color: textColor}, baselineColor: isDark ? '#3d4553' : '#ccc'},
        vAxis: {textStyle: {color: textColor}, baselineColor: isDark ? '#3d4553' : '#ccc'},
        annotations: {
            alwaysOutside: true,
            textStyle: { 
                fontSize: 12, 
                color: annotationColor 
            }
        }
    };
    var distChart = new google.visualization.BarChart(document.getElementById('distributionChart'));
    distChart.draw(distData, distOptions);
    @endif
}
</script>
@endsection