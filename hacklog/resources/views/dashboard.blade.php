@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container">
    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="display-4">Welcome back, {{ Auth::user()->name }}</h1>
            <p class="lead text-muted">Here's what you need to focus on right now.</p>
        </div>
    </div>

    <div class="row">
        <!-- Assigned Work -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-0">Your Assigned Work</h2>
                </div>
                <div class="card-body">
                    @if($assignedTasks->isEmpty())
                        <p class="text-muted mb-0">No tasks assigned to you yet.</p>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($assignedTasks as $task)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1">
                                                <a href="{{ route('projects.board', $task->epic->project) }}" class="text-decoration-none">
                                                    {{ $task->title }}
                                                </a>
                                            </h6>
                                            <p class="mb-1 text-muted small">
                                                {{ $task->epic->project->name }} › {{ $task->epic->name }}
                                            </p>
                                            @if($task->due_date)
                                                <span class="badge {{ $task->isOverdue() ? 'bg-danger' : 'bg-secondary' }}">
                                                    Due {{ $task->due_date->format('M j, Y') }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="ms-3">
                                            <span class="badge bg-light text-dark">{{ ucfirst($task->status) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Upcoming Deadlines -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">Upcoming Deadlines</h3>
                </div>
                <div class="card-body">
                    @if($upcomingDeadlineTasks->isEmpty())
                        <p class="text-muted mb-0">No upcoming deadlines.</p>
                    @else
                        @foreach($upcomingDeadlineTasks as $date => $tasks)
                            <div class="mb-3">
                                <h6 class="mb-2 text-primary">{{ \Carbon\Carbon::parse($date)->format('M j, Y') }}</h6>
                                <ul class="list-unstyled ms-3">
                                    @foreach($tasks as $task)
                                        <li class="mb-1">
                                            <small>
                                                <a href="{{ route('projects.board', $task->epic->project) }}" class="text-decoration-none">
                                                    {{ $task->title }}
                                                </a>
                                                <span class="text-muted">in {{ $task->epic->project->name }}</span>
                                            </small>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            <!-- Quick Navigation -->
            <div class="card">
                <div class="card-header">
                    <h3 class="h5 mb-0">Quick Navigation</h3>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('projects.index') }}" class="btn btn-outline-primary">View All Projects</a>
                        <a href="{{ route('schedule.index') }}" class="btn btn-outline-primary">Organization Schedule</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection