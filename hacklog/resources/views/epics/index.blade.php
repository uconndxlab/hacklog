@extends('layouts.app')

@section('title', 'Epics - ' . $project->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.edit', $project) }}">Settings</a></li>
        <li class="breadcrumb-item active" aria-current="page">Manage Epics</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="mb-1">Epics</h1>
        <p class="text-muted mb-0">{{ $project->name }}</p>
    </div>
    <a href="{{ route('projects.epics.create', $project) }}" class="btn btn-primary">New Epic</a>
</div>

@if($epics->isEmpty())
    @include('partials.empty-state', [
        'message' => 'No epics yet. Epics are large features or phases that contain multiple related tasks.',
        'actionUrl' => route('projects.epics.create', $project),
        'actionText' => 'Create your first epic'
    ])
@else
    <div class="list-group">
        @foreach($epics as $epic)
            <div class="list-group-item">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <h2 class="h5 mb-2">{{ $epic->name }}</h2>
                        <p class="mb-1">
                            <span class="badge 
                                @if($epic->status === 'planned') bg-secondary
                                @elseif($epic->status === 'active') bg-success
                                @else bg-primary
                                @endif">
                                {{ ucfirst($epic->status) }}
                            </span>
                            @if($epic->start_date || $epic->end_date)
                                <span class="text-muted ms-2">
                                    @if($epic->start_date)
                                        {{ $epic->start_date->format('M j, Y') }}
                                    @endif
                                    @if($epic->start_date && $epic->end_date)
                                        →
                                    @endif
                                    @if($epic->end_date)
                                        {{ $epic->end_date->format('M j, Y') }}
                                    @endif
                                </span>
                            @endif
                        </p>
                        @if($epic->description)
                            <p class="mb-1 text-muted small">
                                {{ Str::limit(strip_tags($epic->description), 150) }}
                            </p>
                        @endif
                    </div>
                    <div class="ms-3">
                        <a href="{{ route('projects.epics.show', [$project, $epic]) }}" class="btn btn-sm btn-outline-primary">View</a>
                        <a href="{{ route('projects.epics.edit', [$project, $epic]) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
