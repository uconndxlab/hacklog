@extends('layouts.app')

@section('title', 'Kanban Columns - ' . $project->name)

@section('content')
@include('projects.partials.project-header')
@include('projects.partials.project-nav', ['currentView' => 'settings'])

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h4 mb-0">Board Columns</h2>
    <button 
        class="btn btn-primary" 
        hx-get="{{ route('projects.columns.create', $project) }}"
        hx-target="#create-form-container"
        hx-swap="innerHTML">
        Create Column
    </button>
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
@endsection
