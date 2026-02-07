@extends('layouts.app')

@section('title', $project->name . ' - Board')

@section('content')
@include('projects.partials.project-nav', ['currentView' => 'board'])

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <h1 class="mb-0">{{ $project->name }}</h1>
        @if(request('epic'))
            @php
                $filteredEpic = $epics->firstWhere('id', request('epic'));
            @endphp
            @if($filteredEpic)
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary">
                        {{ $filteredEpic->name }}
                        <a href="{{ route('projects.board', $project) }}" class="text-white text-decoration-none ms-1">×</a>
                    </span>
                    <button 
                        type="button" 
                        class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" 
                        data-bs-target="#epicInfoModal">
                        View Details
                    </button>
                </div>
            @endif
        @endif
    </div>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                Filter by Epic
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="{{ route('projects.board', $project) }}">All Epics</a></li>
                <li><hr class="dropdown-divider"></li>
                @foreach($epics as $epic)
                    <li>
                        <a class="dropdown-item {{ request('epic') == $epic->id ? 'active' : '' }}" 
                           href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}">
                            {{ $epic->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                @php
                    $assigned = request('assigned');
                    $assignedLabel = 'All Tasks';
                    if ($assigned === 'me') {
                        $assignedLabel = 'Assigned to Me';
                    } elseif ($assigned === 'none') {
                        $assignedLabel = 'Unassigned';
                    } elseif ($assigned && is_numeric($assigned)) {
                        $assignedUser = \App\Models\User::find($assigned);
                        $assignedLabel = $assignedUser ? 'Assigned to ' . $assignedUser->name : 'All Tasks';
                    }
                @endphp
                {{ $assignedLabel }}
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item {{ !request('assigned') ? 'active' : '' }}" 
                       href="{{ request()->fullUrlWithQuery(['assigned' => null]) }}">All Tasks</a></li>
                <li><a class="dropdown-item {{ request('assigned') === 'me' ? 'active' : '' }}" 
                       href="{{ request()->fullUrlWithQuery(['assigned' => 'me']) }}">Assigned to Me</a></li>
                <li><a class="dropdown-item {{ request('assigned') === 'none' ? 'active' : '' }}" 
                       href="{{ request()->fullUrlWithQuery(['assigned' => 'none']) }}">Unassigned</a></li>
                <li><hr class="dropdown-divider"></li>
                @php
                    $users = \App\Models\User::orderBy('name')->get();
                @endphp
                @foreach($users as $user)
                    <li>
                        <a class="dropdown-item {{ request('assigned') == $user->id ? 'active' : '' }}" 
                           href="{{ request()->fullUrlWithQuery(['assigned' => $user->id]) }}">
                            {{ $user->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <a href="{{ route('projects.columns.index', $project) }}" class="btn btn-sm btn-outline-secondary">Manage Columns</a>
    </div>
</div>

@if($columns->isEmpty())
    @include('partials.empty-state', [
        'message' => 'No columns configured yet. Set up your kanban workflow by creating columns like "To Do", "In Progress", and "Done".',
        'actionUrl' => route('projects.columns.create', $project),
        'actionText' => 'Create your first column'
    ])
@else
    <div class="board-container" id="board-container">
        @foreach($columns as $column)
            <div class="board-column-wrapper">
                @include('projects.partials.board-column', [
                    'project' => $project,
                    'column' => $column,
                    'columnTasks' => $tasks->get($column->id, collect()),
                    'allColumns' => $columns,
                    'isProjectBoard' => true
                ])
            </div>
        @endforeach
    </div>
@endif

{{-- Task Creation/Edit Modal --}}
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskModalLabel">Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="taskModalContent">
                {{-- Content loaded via HTMX --}}
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Task Details Modal --}}
<div class="modal fade" id="taskDetailsModal" tabindex="-1" aria-labelledby="taskDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="taskDetailsModalLabel">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="taskDetailsModalContent">
                {{-- Content loaded via HTMX --}}
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Epic Information Modal --}}
@if(request('epic') && isset($filteredEpic))
<div class="modal fade" id="epicInfoModal" tabindex="-1" aria-labelledby="epicInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="epicInfoModalLabel">{{ $filteredEpic->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <span class="badge 
                        @if($filteredEpic->status === 'planned') bg-secondary
                        @elseif($filteredEpic->status === 'active') bg-success
                        @else bg-primary
                        @endif">
                        {{ ucfirst($filteredEpic->status) }}
                    </span>
                </div>

                @if($filteredEpic->description)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Description</h6>
                        </div>
                        <div class="card-body">
                            <div class="trix-content">
                                {!! $filteredEpic->description !!}
                            </div>
                        </div>
                    </div>
                @endif

                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">Details</h6>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Project</dt>
                            <dd class="col-sm-9">
                                <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                            </dd>

                            @if($filteredEpic->start_date)
                                <dt class="col-sm-3">Start Date</dt>
                                <dd class="col-sm-9">{{ $filteredEpic->start_date->format('F j, Y') }}</dd>
                            @endif

                            @if($filteredEpic->end_date)
                                <dt class="col-sm-3">End Date</dt>
                                <dd class="col-sm-9">{{ $filteredEpic->end_date->format('F j, Y') }}</dd>
                            @endif

                            <dt class="col-sm-3">Created</dt>
                            <dd class="col-sm-9">{{ $filteredEpic->created_at->format('F j, Y \a\t g:i A') }}</dd>

                            <dt class="col-sm-3">Last Updated</dt>
                            <dd class="col-sm-9 mb-0">{{ $filteredEpic->updated_at->format('F j, Y \a\t g:i A') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('projects.epics.edit', [$project, $filteredEpic]) }}" class="btn btn-primary">Edit Epic</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif

<script>
// Add current filter parameters to HTMX requests
document.body.addEventListener('htmx:configRequest', function(evt) {
    const params = new URLSearchParams(window.location.search);
    
    // Add epic filter if present
    if (params.get('epic')) {
        evt.detail.parameters['epic'] = params.get('epic');
    }
    
    // Add assigned filter if present
    if (params.get('assigned')) {
        evt.detail.parameters['assigned'] = params.get('assigned');
    }
});
</script>

<style>
.board-container {
    display: flex;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 1rem;
    gap: 1rem;
}

.board-column-wrapper {
    flex: 0 0 320px;
    min-width: 320px;
    max-width: 320px;
}

/* Hide scrollbar on webkit browsers */
.board-container::-webkit-scrollbar {
    height: 8px;
}

.board-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.board-container::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 4px;
}

.board-container::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}
</style>
@endsection
