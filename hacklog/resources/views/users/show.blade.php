@extends('layouts.app')

@section('title', $user->name . ' - User Profile')

@section('content')
<div class="row">
    <div class="col-lg-12">
        {{-- Breadcrumb Navigation --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
            </ol>
        </nav>

        {{-- Header --}}
        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="mb-1">{{ $user->name }}</h1>
                    <div class="d-flex gap-2 align-items-center mb-2">
                        <span class="badge 
                            @if($user->isAdmin()) bg-danger
                            @elseif($user->isClient()) bg-info
                            @else bg-secondary
                            @endif">
                            {{ ucfirst($user->role) }}
                        </span>
                        @if($user->isActive())
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-warning">Inactive</span>
                        @endif
                    </div>
                    <p class="text-muted mb-0">
                        Joined {{ $user->created_at->format('F j, Y') }}
                        @if($user->most_recent_activity)
                            · Last activity {{ $user->most_recent_activity->diffForHumans() }}
                        @endif
                    </p>
                </div>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary">Edit User</a>
                @endif
            </div>
        </div>

        {{-- Current Workload Snapshot --}}
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">Current Workload</h5>
                <p class="text-muted small mb-3">Active tasks in the next 30 days</p>
                
                <div class="row g-3">
                    <div class="col-md-4 col-lg-2">
                        <div class="border rounded p-3 text-center">
                            <div class="h4 mb-1 text-primary">{{ $totalActiveTasks }}</div>
                            <div class="small text-muted">Active Tasks</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="border rounded p-3 text-center">
                            <div class="h4 mb-1 text-info">{{ $inProgressTasks }}</div>
                            <div class="small text-muted">In Progress</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="border rounded p-3 text-center">
                            <div class="h4 mb-1 text-danger">{{ $overdueTasks }}</div>
                            <div class="small text-muted">Overdue</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="border rounded p-3 text-center">
                            <div class="h4 mb-1 text-warning">{{ $dueNext7Days }}</div>
                            <div class="small text-muted">Due Next 7 Days</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="border rounded p-3 text-center">
                            <div class="h4 mb-1 text-secondary">{{ $distinctActiveProjects }}</div>
                            <div class="small text-muted">Active Projects</div>
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-2">
                        <div class="border rounded p-3 text-center">
                            <div class="h4 mb-1 text-muted">{{ $tasksWithoutDueDates }}</div>
                            <div class="small text-muted">No Due Date</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            {{-- Activity Footprint --}}
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Footprint</h5>
                        <p class="text-muted small mb-3">All-time task involvement</p>
                        
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Total Tasks Assigned</span>
                                <span class="fw-medium">{{ $totalTasksAssigned }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Planned</span>
                                <span class="fw-medium">{{ $tasksPlanned }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Active</span>
                                <span class="fw-medium">{{ $tasksActive }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Completed</span>
                                <span class="fw-medium">{{ $tasksCompleted }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Distinct Projects</span>
                                <span class="fw-medium">{{ $distinctProjectsLifetime }}</span>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Tasks Created</span>
                                <span class="fw-medium">{{ $totalTasksCreated }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Activity Snapshot --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Activity Snapshot</h5>
                        <p class="text-muted small mb-3">Engagement in the last 30 days</p>
                        
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Tasks Created</span>
                                <span class="fw-medium">{{ $tasksCreatedLast30 }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Tasks Updated</span>
                                <span class="fw-medium">{{ $tasksUpdatedLast30 }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom">
                                <span class="text-muted">Comments Posted</span>
                                <span class="fw-medium">{{ $commentsPostedLast30 }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Tasks Completed</span>
                                <span class="fw-medium">{{ $tasksCompletedLast30 }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="row mb-4">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Status Breakdown</h5>
                        <p class="text-muted small mb-3">All currently assigned tasks</p>
                        <div id="status-chart" style="height: 280px;"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Due Pressure</h5>
                        <p class="text-muted small mb-3">Timeline of active task due dates</p>
                        <div id="due-pressure-chart" style="height: 280px;"></div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Active Projects List --}}
        @if($activeProjectsData->isNotEmpty())
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Active Projects</h5>
                <p class="text-muted small mb-3">Projects with open or in-progress tasks</p>
                
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Project</th>
                                <th class="text-end">Active Tasks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activeProjectsData as $projectData)
                            <tr>
                                <td>
                                    <a href="{{ route('projects.board', $projectData['project']) }}" class="text-decoration-none">
                                        {{ $projectData['project']->name }}
                                    </a>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-secondary">{{ $projectData['active_task_count'] }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body text-center text-muted py-5">
                <p class="mb-0">No active projects at this time</p>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Google Charts --}}
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawCharts);

    function drawCharts() {
        // Status Breakdown Donut Chart
        var statusData = google.visualization.arrayToDataTable([
            ['Status', 'Count'],
            ['Open', {{ $statusBreakdown['open'] }}],
            ['In Progress', {{ $statusBreakdown['in_progress'] }}],
            ['Done', {{ $statusBreakdown['done'] }}]
        ]);

        var statusOptions = {
            pieHole: 0.4,
            colors: ['#6c757d', '#0dcaf0', '#198754'],
            legend: { position: 'bottom' },
            chartArea: { width: '90%', height: '70%' },
            backgroundColor: 'transparent',
            pieSliceText: 'value'
        };

        var statusChart = new google.visualization.PieChart(document.getElementById('status-chart'));
        statusChart.draw(statusData, statusOptions);

        // Due Pressure Bar Chart
        var duePressureData = google.visualization.arrayToDataTable([
            ['Period', 'Tasks', { role: 'style' }],
            ['Overdue', {{ $duePressure['overdue'] }}, '#dc3545'],
            ['Next 7 Days', {{ $duePressure['next_7_days'] }}, '#ffc107'],
            ['Next 14 Days', {{ $duePressure['next_14_days'] }}, '#0dcaf0'],
            ['Later', {{ $duePressure['later'] }}, '#6c757d']
        ]);

        var duePressureOptions = {
            legend: { position: 'none' },
            chartArea: { width: '80%', height: '70%' },
            backgroundColor: 'transparent',
            hAxis: {
                minValue: 0,
                format: '0'
            },
            vAxis: {
                textStyle: { fontSize: 12 }
            }
        };

        var duePressureChart = new google.visualization.BarChart(document.getElementById('due-pressure-chart'));
        duePressureChart.draw(duePressureData, duePressureOptions);
    }

    // Redraw charts on window resize
    window.addEventListener('resize', function() {
        drawCharts();
    });
</script>
@endsection
