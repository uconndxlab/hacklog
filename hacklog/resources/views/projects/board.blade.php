@extends('layouts.app')

@section('title', $project->name . ' - Board')

@section('content')
@include('projects.partials.project-header')
@include('projects.partials.project-nav', ['currentView' => 'board'])

{{-- Page Actions --}}
<div class="d-flex justify-content-between align-items-center mb-4">

    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-label="Filter by Phase">
                @php
                    $filteredPhase = request('phase') ? $phases->firstWhere('id', request('phase')) : null;
                @endphp
                @if($filteredPhase)
                    {{ $filteredPhase->name }}
                @else
                    All Phases
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item {{ !request('phase') ? 'active' : '' }}" href="{{ route('projects.board', $project) }}">All Phases</a></li>
                <li><hr class="dropdown-divider"></li>
                @foreach($phases as $phase)
                    <li>
                        <a class="dropdown-item {{ request('phase') == $phase->id ? 'active' : '' }}" 
                           href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}">
                            {{ $phase->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-label="Filter by assignee">
                @php
                    $assigned = request('assigned');
                    $assignedLabel = 'All Assignments';
                    if ($assigned === 'me') {
                        $assignedLabel = 'Assigned to Me';
                    } elseif ($assigned === 'none') {
                        $assignedLabel = 'Unassigned';
                    } elseif ($assigned && is_numeric($assigned)) {
                        $assignedUser = \App\Models\User::find($assigned);
                        if ($assignedUser) {
                            $assignedLabel = 'Assigned to ' . $assignedUser->name;
                        }
                    }
                @endphp
                {{ $assignedLabel }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item {{ !request('assigned') ? 'active' : '' }}" 
                       href="{{ request()->fullUrlWithQuery(['assigned' => null]) }}">All Tasks</a></li>
                <li><a class="dropdown-item {{ request('assigned') === 'me' ? 'active' : '' }}" 
                       href="{{ request()->fullUrlWithQuery(['assigned' => 'me']) }}">Assigned to Me</a></li>
                <li><a class="dropdown-item {{ request('assigned') === 'none' ? 'active' : '' }}" 
                       href="{{ request()->fullUrlWithQuery(['assigned' => 'none']) }}">Unassigned</a></li>
                <li><hr class="dropdown-divider"></li>
                @foreach($usersWithTasks as $user)
                    <li>
                        <a class="dropdown-item {{ request('assigned') == $user->id ? 'active' : '' }}" 
                           href="{{ request()->fullUrlWithQuery(['assigned' => $user->id]) }}">
                            {{ $user->name }} ({{ $user->tasks_count }})
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

{{-- Phase Synopsis --}}
@if($phaseSynopsis)
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="card-title mb-2">{{ $phaseSynopsis->name }}</h5>
                    <div class="text-muted small mb-2">
                        @if($phaseSynopsis->start_date && $phaseSynopsis->end_date)
                            {{ $phaseSynopsis->start_date->format('M j, Y') }} – {{ $phaseSynopsis->end_date->format('M j, Y') }}
                        @elseif($phaseSynopsis->start_date)
                            Starts: {{ $phaseSynopsis->start_date->format('M j, Y') }}
                        @elseif($phaseSynopsis->end_date)
                            Ends: {{ $phaseSynopsis->end_date->format('M j, Y') }}
                        @else
                            No dates set
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <div class="small text-muted mb-1">
                            {{ $phaseSynopsis->completed_tasks_count }} of {{ $phaseSynopsis->tasks_count }} tasks completed
                        </div>
                        @php
                            $completionPercentage = $phaseSynopsis->tasks_count > 0 
                                ? round(($phaseSynopsis->completed_tasks_count / $phaseSynopsis->tasks_count) * 100) 
                                : 0;
                        @endphp
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar {{ $completionPercentage == 100 ? 'bg-success' : ($completionPercentage > 50 ? 'bg-info' : 'bg-warning') }}" 
                                 role="progressbar" 
                                 style="width: {{ $completionPercentage }}%" 
                                 aria-valuenow="{{ $completionPercentage }}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        <div class="small text-muted mt-1">{{ $completionPercentage }}% complete</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

@if($columns->isEmpty())
    <div class="alert alert-light border text-center">
        <h5 class="alert-heading">This project has no columns yet.</h5>
        <p class="mb-3">Get started quickly with a standard kanban workflow or create custom columns.</p>
        <div class="d-flex justify-content-center gap-2">
            <form method="POST" action="{{ route('projects.board.create-default-columns', $project) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-primary">Use default columns</button>
            </form>
            <a href="{{ route('projects.columns.create', $project) }}" class="btn btn-outline-secondary">Create custom column</a>
        </div>
        <div class="mt-2 text-muted small">
            Default columns: Backlog → In Progress → Ready for Testing → Completed
        </div>
    </div>
@else
    <div class="board-container" id="board-container">
        @foreach($columns as $column)
            <div class="board-column-wrapper">
                @include('projects.partials.board-column', [
                    'project' => $project,
                    'column' => $column,
                    'columnTasks' => $tasks->get($column->id, collect()),
                    'allColumns' => $columns,
                    'isProjectBoard' => true,
                    'filterPhaseId' => request('phase')
                ])
            </div>
        @endforeach
    </div>
@endif

{{-- Task Creation/Edit Modal --}}
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="flex-shrink: 0;">
                <h5 class="modal-title" id="taskModalLabel">Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="taskModalContent" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column;">
                {{-- Content loaded via HTMX --}}
                <div class="text-center py-4" style="flex: 1; display: flex; align-items: center; justify-content: center;">
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
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header" style="flex-shrink: 0;">
                <h5 class="modal-title" id="taskDetailsModalLabel">Task Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="taskDetailsModalContent" style="flex: 1; overflow-y: auto;">
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

{{-- Phase Information Modal --}}
@if(request('phase') && isset($filteredPhase))
<div class="modal fade" id="phaseInfoModal" tabindex="-1" aria-labelledby="phaseInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="phaseInfoModalLabel">{{ $filteredPhase->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <span class="badge 
                        @if($filteredPhase->status === 'planned') bg-secondary
                        @elseif($filteredPhase->status === 'active') bg-success
                        @else bg-primary
                        @endif">
                        {{ ucfirst($filteredPhase->status) }}
                    </span>
                </div>

                @if($filteredPhase->description)
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Description</h6>
                        </div>
                        <div class="card-body">
                            <div class="trix-content">
                                {!! $filteredPhase->description !!}
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

                            @if($filteredPhase->start_date)
                                <dt class="col-sm-3">Start Date</dt>
                                <dd class="col-sm-9">{{ $filteredPhase->start_date->format('F j, Y') }}</dd>
                            @endif

                            @if($filteredPhase->end_date)
                                <dt class="col-sm-3">End Date</dt>
                                <dd class="col-sm-9">{{ $filteredPhase->end_date->format('F j, Y') }}</dd>
                            @endif

                            <dt class="col-sm-3">Created</dt>
                            <dd class="col-sm-9">{{ $filteredPhase->created_at->format('F j, Y \a\t g:i A') }}</dd>

                            <dt class="col-sm-3">Last Updated</dt>
                            <dd class="col-sm-9 mb-0">{{ $filteredPhase->updated_at->format('F j, Y \a\t g:i A') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="{{ route('projects.phases.edit', [$project, $filteredPhase]) }}" class="btn btn-primary">Edit Phase</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- Auto-open task modal if task parameter is present in URL --}}
{{-- This enables deep linking from Dashboard or other views --}}
@if(request()->has('task'))
    @php
        // Tasks belong to columns, so we need to find the task through the project's columns
        $targetTask = \App\Models\Task::whereHas('column', function($query) use ($project) {
            $query->where('project_id', $project->id);
        })->find(request()->get('task'));
    @endphp
    
    @if($targetTask)
        {{-- Hidden trigger that loads and opens the task modal on page load --}}
        <div 
            hx-get="{{ route('projects.board.tasks.edit', [$project, $targetTask]) }}" 
            hx-target="#taskModalContent"
            hx-trigger="load"
            hx-on::after-request="
                const modal = new bootstrap.Modal(document.getElementById('taskModal'));
                modal.show();
                const url = new URL(window.location);
                url.searchParams.delete('task');
                window.history.replaceState({}, '', url);
            "
            style="display: none;">
        </div>
    @endif
@endif

<script>
// Add current filter parameters and CSRF token to HTMX requests
document.body.addEventListener('htmx:configRequest', function(evt) {
    const params = new URLSearchParams(window.location.search);
    
    // Add phase filter if present
    if (params.get('phase')) {
        evt.detail.parameters['phase'] = params.get('phase');
    }
    
    // Add assigned filter if present
    if (params.get('assigned')) {
        evt.detail.parameters['assigned'] = params.get('assigned');
    }
    
    // Add CSRF token to all HTMX requests
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
        evt.detail.headers['X-CSRF-TOKEN'] = token.getAttribute('content');
    }
});

// Auto-focus title input when task form loads in modal
document.body.addEventListener('htmx:afterSwap', function(evt) {
    // Check if we're swapping into the task modal
    if (evt.detail.target.id === 'taskModalContent') {
        // Wait for modal to be fully shown before focusing
        const modal = document.getElementById('taskModal');
        if (modal) {
            const handleShown = function() {
                const titleInput = document.getElementById('title');
                if (titleInput) {
                    titleInput.focus();
                }
                modal.removeEventListener('shown.bs.modal', handleShown);
            };
            modal.addEventListener('shown.bs.modal', handleShown);
        }
    }
});

// Esc key closes modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const taskModal = bootstrap.Modal.getInstance(document.getElementById('taskModal'));
        if (taskModal) {
            taskModal.hide();
        }
        const detailsModal = bootstrap.Modal.getInstance(document.getElementById('taskDetailsModal'));
        if (detailsModal) {
            detailsModal.hide();
        }
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

/* When there are 3 or fewer columns, make them take full width */
.board-container:has(.board-column-wrapper:nth-child(-n+4):last-child) .board-column-wrapper {
    flex: 1 1 0;
    max-width: none;
}

/* Drag & Drop Styles */
.board-column.drop-target {
    outline: 2px dashed #007bff;
    outline-offset: -2px;
    background-color: rgba(0, 123, 255, 0.05);
}

.task-card.dragging {
    transform: rotate(2deg);
    z-index: 1000;
}

.insertion-indicator {
    pointer-events: none;
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

<script>
// Drag & Drop functionality for task reordering and moving
(function() {
    let draggedTask = null;
    let placeholder = null;
    let originalPosition = null;

    // Create insertion indicator
    function createInsertionIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'insertion-indicator';
        indicator.style.cssText = `
            height: 2px;
            background-color: #007bff;
            margin: 4px 0;
            border-radius: 1px;
            opacity: 0;
            transition: opacity 0.2s ease;
        `;
        return indicator;
    }

    // Show insertion indicator at position
    function showInsertionIndicator(container, beforeElement) {
        if (placeholder) {
            placeholder.remove();
        }
        placeholder = createInsertionIndicator();
        if (beforeElement) {
            container.insertBefore(placeholder, beforeElement);
        } else {
            container.appendChild(placeholder);
        }
        setTimeout(() => placeholder.style.opacity = '1', 0);
    }

    // Hide insertion indicator
    function hideInsertionIndicator() {
        if (placeholder) {
            placeholder.style.opacity = '0';
            setTimeout(() => {
                if (placeholder) placeholder.remove();
                placeholder = null;
            }, 200);
        }
    }

    // Get drop position based on mouse Y coordinate
    function getDropPosition(container, clientY) {
        const taskCards = Array.from(container.querySelectorAll('.task-card:not(.dragging)'));
        if (taskCards.length === 0) return 0;

        for (let i = 0; i < taskCards.length; i++) {
            const rect = taskCards[i].getBoundingClientRect();
            if (clientY < rect.top + rect.height / 2) {
                return i;
            }
        }
        return taskCards.length;
    }

    // Drag start
    document.addEventListener('dragstart', function(e) {
        if (!e.target.classList.contains('task-card')) return;

        draggedTask = e.target;
        originalPosition = {
            columnId: draggedTask.dataset.columnId,
            position: parseInt(draggedTask.dataset.position)
        };

        // Reduce opacity
        draggedTask.style.opacity = '0.5';
        draggedTask.classList.add('dragging');

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', draggedTask.dataset.taskId);
    });

    // Drag over
    document.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const column = e.target.closest('.board-column');
        if (!column) return;

        const taskContainer = column.querySelector('[id^="board-column-"][id$="-tasks"]');
        if (!taskContainer) return;

        // Highlight column
        document.querySelectorAll('.board-column').forEach(col => col.classList.remove('drop-target'));
        column.classList.add('drop-target');

        // Show insertion indicator
        const position = getDropPosition(taskContainer, e.clientY);
        const taskCards = Array.from(taskContainer.querySelectorAll('.task-card:not(.dragging)'));
        const beforeElement = taskCards[position] || null;
        showInsertionIndicator(taskContainer, beforeElement);
    });

    // Drag leave
    document.addEventListener('dragleave', function(e) {
        // Only hide if leaving the column entirely
        const column = e.target.closest('.board-column');
        const relatedColumn = e.relatedTarget ? e.relatedTarget.closest('.board-column') : null;
        if (column && !relatedColumn) {
            column.classList.remove('drop-target');
            hideInsertionIndicator();
        }
    });

    // Drop
    document.addEventListener('drop', function(e) {
        e.preventDefault();

        if (!draggedTask) return;

        const column = e.target.closest('.board-column');
        if (!column) return;

        const taskContainer = column.querySelector('[id^="board-column-"][id$="-tasks"]');
        if (!taskContainer) return;

        const newColumnId = column.dataset.columnId;
        const position = getDropPosition(taskContainer, e.clientY);

        // If same column and same position, do nothing
        if (newColumnId === originalPosition.columnId && position === originalPosition.position) {
            resetDragState();
            return;
        }

        // Move task in DOM
        const taskCards = Array.from(taskContainer.querySelectorAll('.task-card:not(.dragging)'));
        const beforeElement = taskCards[position] || null;
        if (beforeElement) {
            taskContainer.insertBefore(draggedTask, beforeElement);
        } else {
            taskContainer.appendChild(draggedTask);
        }

        // Remove empty state if it exists
        const emptyState = taskContainer.querySelector('.text-muted.text-center');
        if (emptyState) {
            emptyState.remove();
        }

        // Update data attributes
        draggedTask.dataset.columnId = newColumnId;
        draggedTask.dataset.position = position;

        // Send to server
        const requestBody = {
            column_id: newColumnId,
            position: position
        };
        
        // Include phase filter if active
        const urlParams = new URLSearchParams(window.location.search);
        const filterPhaseId = urlParams.get('phase');
        if (filterPhaseId) {
            requestBody.filter_phase_id = filterPhaseId;
        }
        
        fetch(`/projects/{{ $project->id }}/board/tasks/${draggedTask.dataset.taskId}/move`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(requestBody)
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                // Revert on failure
                revertTaskPosition();
            } else if (data.columnChanged) {
                // Update both columns with fresh HTML
                const oldColumnContainer = document.getElementById(`board-column-${data.oldColumnId}-tasks`);
                const newColumnContainer = document.getElementById(`board-column-${data.newColumnId}-tasks`);
                
                if (oldColumnContainer && data.oldColumnHtml) {
                    oldColumnContainer.outerHTML = data.oldColumnHtml;
                    // Re-process the new HTML with HTMX
                    const newOldColumn = document.getElementById(`board-column-${data.oldColumnId}-tasks`);
                    if (newOldColumn && typeof htmx !== 'undefined') {
                        htmx.process(newOldColumn);
                    }
                }
                if (newColumnContainer && data.newColumnHtml) {
                    newColumnContainer.outerHTML = data.newColumnHtml;
                    // Re-process the new HTML with HTMX
                    const newNewColumn = document.getElementById(`board-column-${data.newColumnId}-tasks`);
                    if (newNewColumn && typeof htmx !== 'undefined') {
                        htmx.process(newNewColumn);
                    }
                }
            }
        })
        .catch(() => {
            // Revert on error
            revertTaskPosition();
        })
        .finally(() => {
            resetDragState();
        });
    });

    // Drag end
    document.addEventListener('dragend', function(e) {
        resetDragState();
    });

    function resetDragState() {
        if (draggedTask) {
            draggedTask.style.opacity = '';
            draggedTask.classList.remove('dragging');
        }
        document.querySelectorAll('.board-column').forEach(col => col.classList.remove('drop-target'));
        hideInsertionIndicator();
        draggedTask = null;
        originalPosition = null;
    }

    function revertTaskPosition() {
        // Simple revert: reload the board
        window.location.reload();
    }
})();
</script>
@endsection
