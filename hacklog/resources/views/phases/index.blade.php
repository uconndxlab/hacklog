@extends('layouts.app')

@section('title', 'Phases - ' . $project->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.edit', $project) }}">Settings</a></li>
        <li class="breadcrumb-item active" aria-current="page">Manage Phases</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="mb-1">Phases</h1>
        <p class="text-muted mb-0">{{ $project->name }}</p>
    </div>
    <a href="{{ route('projects.phases.create', $project) }}" class="btn btn-primary">New Phase</a>
</div>

@if($phases->isEmpty())
    @include('partials.empty-state', [
        'message' => 'No phases yet. Phases are large features or phases that contain multiple related tasks.',
        'actionUrl' => route('projects.phases.create', $project),
        'actionText' => 'Create your first phase'
    ])
@else
    <div class="list-group">
        @foreach($phases as $phase)
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h2 class="h5 mb-2">{{ $phase->name }}</h2>
                        <p class="mb-1">
                            <span class="badge 
                                @if($phase->status === 'planned') bg-secondary
                                @elseif($phase->status === 'active') bg-success
                                @else bg-primary
                                @endif">
                                {{ ucfirst($phase->status) }}
                            </span>
                            @if($phase->start_date || $phase->end_date)
                                <span class="text-muted ms-2">
                                    @if($phase->start_date)
                                        {{ $phase->start_date->format('M j, Y') }}
                                    @endif
                                    @if($phase->start_date && $phase->end_date)
                                        →
                                    @endif
                                    @if($phase->end_date)
                                        {{ $phase->end_date->format('M j, Y') }}
                                    @endif
                                </span>
                            @endif
                        </p>
                        @if($phase->description)
                            <p class="mb-1 text-muted small">
                                {{ Str::limit(strip_tags($phase->description), 150) }}
                            </p>
                        @endif
                    </div>
                    <div class="ms-3">
                        <a href="{{ route('projects.phases.show', [$project, $phase]) }}" class="btn btn-sm btn-outline-primary">View</a>
                        <a href="{{ route('projects.phases.edit', [$project, $phase]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
