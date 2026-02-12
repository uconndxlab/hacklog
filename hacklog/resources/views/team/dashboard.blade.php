@extends('layouts.app')

@section('title', 'Team Dashboard')

@section('content')
    <div class="row mb-4">
        <div class="col">
            <h1 class="h3 mb-3">Team Dashboard</h1>
            <p class="text-muted">Situational awareness of team workload and schedule pressure</p>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ url('/team') }}" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date"
                                value="{{ $startDate }}">
                        </div>
                        <div class="col-md-4">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date"
                                value="{{ $endDate }}">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">Apply Filter</button>
                            <a href="{{ url('/team') }}" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Team Member Cards -->
    <div class="row g-4">
        @foreach ($teamMetrics as $metrics)
            <div class="col-12 col-lg-6 col-xl-4">
                <div class="card h-100">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0">{{ $metrics['user']->name }}</h5>
                        <small class="text-muted">{{ $metrics['user']->email }}</small>
                    </div>
                    <div class="card-body">
                        @if ($metrics['summary']['total_tasks'] === 0)
                            <p class="text-muted text-center py-4">No tasks in selected date range</p>
                        @else
                            <!-- Tabs -->
                            <ul class="nav nav-tabs mb-3" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="overview-tab-{{ $metrics['user']->id }}"
                                        data-bs-toggle="tab" data-bs-target="#overview-{{ $metrics['user']->id }}"
                                        type="button" role="tab">Overview</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="projects-tab-{{ $metrics['user']->id }}"
                                        data-bs-toggle="tab" data-bs-target="#projects-{{ $metrics['user']->id }}"
                                        type="button" role="tab">Projects
                                        ({{ $metrics['projects']->count() }})</button>
                                </li>
                            </ul>

                            <!-- Tab Content -->
                            <div class="tab-content">
                                <!-- Overview Tab -->
                                <div class="tab-pane fade show active" id="overview-{{ $metrics['user']->id }}"
                                    role="tabpanel">
                                    <!-- Basic Summary -->
                                    <div class="mb-4">
                                        <h6 class="text-uppercase text-muted mb-3"
                                            style="font-size: 0.75rem; font-weight: 600;">Summary</h6>
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold fs-4">{{ $metrics['summary']['total_tasks'] }}
                                                    </div>
                                                    <div class="small text-muted">Total Tasks</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold fs-4">
                                                        {{ $metrics['summary']['distinct_projects'] }}</div>
                                                    <div class="small text-muted">Projects</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold">{{ $metrics['summary']['open_tasks'] }}</div>
                                                    <div class="small text-muted">Planned</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold">{{ $metrics['summary']['in_progress_tasks'] }}
                                                    </div>
                                                    <div class="small text-muted">Active</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold">{{ $metrics['summary']['completed_tasks'] }}</div>
                                                    <div class="small text-muted">Completed</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Status Breakdown Chart -->
                                    <div class="mb-4">
                                        <h6 class="text-uppercase text-muted mb-3"
                                            style="font-size: 0.75rem; font-weight: 600;">Status Breakdown</h6>
                                        <div id="status_chart_{{ $metrics['user']->id }}" style="height: 200px;"></div>
                                        @if (config('app.debug'))
                                            <small class="text-muted">Debug: P={{ $metrics['summary']['open_tasks'] }},
                                                A={{ $metrics['summary']['in_progress_tasks'] }},
                                                C={{ $metrics['summary']['completed_tasks'] }}</small>
                                        @endif
                                    </div>

                                    <!-- Due Pressure -->
                                    <div class="mb-4">
                                        <h6 class="text-uppercase text-muted mb-3"
                                            style="font-size: 0.75rem; font-weight: 600;">Due Date Pressure</h6>
                                        <div class="row g-2 mb-3">
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold text-danger">
                                                        {{ $metrics['due_pressure']['overdue'] }}</div>
                                                    <div class="small text-muted">Overdue</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold">{{ $metrics['due_pressure']['next_7_days'] }}
                                                    </div>
                                                    <div class="small text-muted">Next 7 Days</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold">{{ $metrics['due_pressure']['next_14_days'] }}
                                                    </div>
                                                    <div class="small text-muted">Next 14 Days</div>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="border rounded p-2 text-center">
                                                    <div class="fw-bold">{{ $metrics['due_pressure']['later'] }}</div>
                                                    <div class="small text-muted">Later</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div id="due_pressure_chart_{{ $metrics['user']->id }}" style="height: 200px;">
                                        </div>
                                    </div>


                                </div>

                                <!-- Projects Tab -->
                                <div class="tab-pane fade" id="projects-{{ $metrics['user']->id }}" role="tabpanel">
                                    @if ($metrics['projects']->count() > 0)
                                        <div class="list-group">
                                            @foreach ($metrics['projects'] as $project)
                                                <a href="{{ route('projects.show', $project['id']) }}"
                                                    class="list-group-item list-group-item-action">
                                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                                        <h6 class="mb-1">{{ $project['name'] }}</h6>
                                                        <span
                                                            class="badge bg-primary rounded-pill">{{ $project['active_task_count'] }}
                                                            active</span>
                                                    </div>
                                                    <small class="text-muted">{{ $project['total_task_count'] }} total
                                                        tasks in range</small>
                                                </a>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-muted text-center py-4">No projects with active tasks</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
        google.charts.load('current', {
            'packages': ['corechart']
        });
        google.charts.setOnLoadCallback(drawCharts);

        function drawCharts() {
            console.log('Drawing charts...');
            @foreach ($teamMetrics as $metrics)
                @if ($metrics['summary']['total_tasks'] > 0)
                    console.log(
                    'User {{ $metrics['user']->id }}: Total tasks = {{ $metrics['summary']['total_tasks'] }}');

                    // Status Breakdown Donut Chart
                    var statusData{{ $metrics['user']->id }} = google.visualization.arrayToDataTable([
                        ['Status', 'Count'],
                        ['Planned', {{ $metrics['summary']['open_tasks'] }}],
                        ['Active', {{ $metrics['summary']['in_progress_tasks'] }}],
                        ['Completed', {{ $metrics['summary']['completed_tasks'] }}]
                    ]);

                    var statusOptions{{ $metrics['user']->id }} = {
                        pieHole: 0.4,
                        colors: ['#6c757d', '#0d6efd', '#198754'],
                        legend: {
                            position: 'bottom',
                            textStyle: {
                                fontSize: 11
                            }
                        },
                        chartArea: {
                            width: '90%',
                            height: '70%'
                        },
                        pieSliceText: 'value',
                        tooltip: {
                            textStyle: {
                                fontSize: 12
                            }
                        },
                        backgroundColor: 'transparent'
                    };

                    var statusChart{{ $metrics['user']->id }} = new google.visualization.PieChart(
                        document.getElementById('status_chart_{{ $metrics['user']->id }}')
                    );
                    statusChart{{ $metrics['user']->id }}.draw(statusData{{ $metrics['user']->id }},
                        statusOptions{{ $metrics['user']->id }});

                    // Due Pressure Bar Chart
                    var duePressureData{{ $metrics['user']->id }} = google.visualization.arrayToDataTable([
                        ['Category', 'Tasks', {
                            role: 'style'
                        }],
                        ['Overdue', {{ $metrics['due_pressure']['overdue'] }}, '#dc3545'],
                        ['Next 7 Days', {{ $metrics['due_pressure']['next_7_days'] }}, '#ffc107'],
                        ['Next 14 Days', {{ $metrics['due_pressure']['next_14_days'] }}, '#0dcaf0'],
                        ['Later', {{ $metrics['due_pressure']['later'] }}, '#6c757d']
                    ]);

                    var duePressureOptions{{ $metrics['user']->id }} = {
                        legend: 'none',
                        chartArea: {
                            width: '75%',
                            height: '70%'
                        },
                        hAxis: {
                            minValue: 0,
                            textStyle: {
                                fontSize: 11
                            }
                        },
                        vAxis: {
                            textStyle: {
                                fontSize: 11
                            }
                        }
                    };

                    var duePressureChart{{ $metrics['user']->id }} = new google.visualization.BarChart(
                        document.getElementById('due_pressure_chart_{{ $metrics['user']->id }}')
                    );
                    duePressureChart{{ $metrics['user']->id }}.draw(duePressureData{{ $metrics['user']->id }},
                        duePressureOptions{{ $metrics['user']->id }});
                @endif
            @endforeach
        }

        // Redraw charts on window resize
        window.addEventListener('resize', function() {
            drawCharts();
        });
    </script>
@endpush
