@extends('layouts.app')

@section('title', 'Kanban Columns - ' . $project->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.edit', $project) }}">Settings</a></li>
        <li class="breadcrumb-item active" aria-current="page">Manage Columns</li>
    </ol>
</nav>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 class="mb-1">Kanban Columns</h1>
        <p class="text-muted mb-0">{{ $project->name }}</p>
    </div>
    <div>
        <button 
            class="btn btn-primary" 
            hx-get="{{ route('projects.columns.create', $project) }}"
            hx-target="#create-form-container"
            hx-swap="innerHTML">
            New Column
        </button>
    </div>
</div>

<div id="create-form-container" class="mb-4"></div>

<div class="table-responsive">
    <table class="table table-hover">
        <thead>
            <tr>
                <th>Position</th>
                <th>Name</th>
                <th>Default</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody id="columns-list">
            @forelse($columns as $column)
                @include('columns.partials.column-row', ['project' => $project, 'column' => $column])
            @empty
                <tr id="empty-state">
                    <td colspan="4" class="text-center py-4">
                        <p class="text-muted mb-0">No columns defined yet. Create columns like "To Do", "In Progress", and "Done" to set up your workflow.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary">Back to Project</a>
</div>
@endsection
