@extends('layouts.app')

@section('title', $project->name . ' - Board')

@push('styles')
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

.board-container--fill .board-column-wrapper {
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

.task-card.task-selected {
    outline: 3px solid #0d6efd;
    outline-offset: -1px;
}

#board-container .task-card {
    user-select: none;
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
@endpush

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
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="{{ route('projects.phases.create', $project) }}">+ Add a Phase</a></li>
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

        {{-- Priority filter --}}
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle {{ request('priority') ? 'active' : '' }}" type="button" data-bs-toggle="dropdown" aria-label="Filter by priority">
                @if(request('priority') === 'high') ↑ High
                @elseif(request('priority') === 'medium') ~ Medium
                @elseif(request('priority') === 'low') ↓ Low
                @else Priority
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item {{ !request('priority') ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['priority' => null]) }}">All Priorities</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item {{ request('priority') === 'high'   ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['priority' => 'high']) }}">↑ High</a></li>
                <li><a class="dropdown-item {{ request('priority') === 'medium' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['priority' => 'medium']) }}">~ Medium</a></li>
                <li><a class="dropdown-item {{ request('priority') === 'low'    ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['priority' => 'low']) }}">↓ Low</a></li>
            </ul>
        </div>

        {{-- Weight filter --}}
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle {{ request('weight') ? 'active' : '' }}" type="button" data-bs-toggle="dropdown" aria-label="Filter by weight">
                {{ request('weight') ? strtoupper(request('weight')) : 'Weight' }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item {{ !request('weight') ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['weight' => null]) }}">All Weights</a></li>
                <li><hr class="dropdown-divider"></li>
                @foreach(['xs' => 'XS – trivial', 's' => 'S – small', 'm' => 'M – medium', 'l' => 'L – large', 'xl' => 'XL – heavy'] as $val => $label)
                    <li><a class="dropdown-item {{ request('weight') === $val ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['weight' => $val]) }}">{{ $label }}</a></li>
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
    <span id="board-selection-count" class="visually-hidden" role="status" aria-live="polite"></span>
    <div id="board-move-error" class="alert alert-danger" role="alert" hidden></div>
    <div class="board-container {{ $columns->count() <= 4 ? 'board-container--fill' : '' }}" id="board-container">
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
            <div class="modal-header py-2" style="flex-shrink: 0;">
                <div class="d-flex flex-column">
                    <div class="d-flex align-items-baseline gap-2">
                        <h5 class="modal-title mb-0" id="taskModalLabel">Task</h5>
                        <div id="taskModalIdentifier" style="display: none;"></div>
                    </div>
                    <div id="taskModalProject" style="display: none;"></div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div id="taskModalActions" style="display: none;"></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
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
            data-task-modal-load
            hx-sync="#taskModal:replace"
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

@include('projects.partials.task-modal-loading-script')

<script>
// Board filter state captured at page render time.
// Using PHP-rendered values avoids losing filters when hx-push-url changes the
// browser URL (e.g. when a task title is clicked to open the detail modal).
const boardFilterPhase    = '{{ request('phase') }}';
const boardFilterAssigned = '{{ request('assigned') }}';

// Restore the board URL (with filters) whenever the task modal closes, undoing
// any URL change caused by hx-push-url on task title links.
(function () {
    const boardBaseUrl = '{{ route('projects.board', $project) }}';
    const filterParts  = [];
    if (boardFilterPhase)    filterParts.push('phase='    + encodeURIComponent(boardFilterPhase));
    if (boardFilterAssigned) filterParts.push('assigned=' + encodeURIComponent(boardFilterAssigned));
    @if(request('priority'))
    filterParts.push('priority=' + encodeURIComponent('{{ request('priority') }}'));
    @endif
    @if(request('weight'))
    filterParts.push('weight=' + encodeURIComponent('{{ request('weight') }}'));
    @endif
    const boardUrl = boardBaseUrl + (filterParts.length ? '?' + filterParts.join('&') : '');

    document.getElementById('taskModal').addEventListener('hidden.bs.modal', function () {
        history.replaceState(null, '', boardUrl);
    });
})();

// Add current filter parameters and CSRF token to HTMX requests
document.body.addEventListener('htmx:configRequest', function(evt) {
    // Use the PHP-rendered filter variables instead of window.location.search so
    // that filters are preserved even when the URL has changed (e.g. after opening
    // a task detail modal which pushes its own URL via hx-push-url).
    if (boardFilterPhase) {
        evt.detail.parameters['phase'] = boardFilterPhase;
    }
    
    if (boardFilterAssigned) {
        evt.detail.parameters['assigned'] = boardFilterAssigned;
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

<script>
function updateBoardColumnCount(column) {
    if (!column) return;
    const countEl = column.querySelector('.board-column-count');
    if (!countEl) return;
    const count = column.querySelectorAll('.task-card').length;
    countEl.textContent = count + ' ' + (count === 1 ? 'task' : 'tasks');
}

function updateBoardColumnCountById(columnId) {
    if (!columnId) return;
    updateBoardColumnCount(document.querySelector('.board-column[data-column-id="' + columnId + '"]'));
}

document.body.addEventListener('htmx:afterSwap', function(evt) {
    const target = evt.detail && evt.detail.target;
    if (!target || !target.id) return;
    if (target.id.indexOf('board-column-') === 0 && target.id.indexOf('-tasks') === target.id.length - 6) {
        updateBoardColumnCount(target.closest('.board-column'));
    }
});

// Drag & Drop functionality for task reordering and moving
(function() {
    let draggedTask = null;
    let placeholder = null;
    let draggedTasks = [];
    let saving = false;
    const board = document.getElementById('board-container');
    if (!board) return;
    const selectedIds = new Set();

    function syncSelection() {
        const cards = Array.from(board.querySelectorAll('.task-card'));
        const visibleIds = new Set(cards.map(card => card.dataset.taskId));
        selectedIds.forEach(id => { if (!visibleIds.has(id)) selectedIds.delete(id); });
        cards.forEach(card => card.classList.toggle('task-selected', selectedIds.has(card.dataset.taskId)));
        document.getElementById('board-selection-count').textContent = selectedIds.size ? `${selectedIds.size} selected` : '';
    }

    function clearSelection() {
        selectedIds.clear();
        syncSelection();
    }

    document.addEventListener('keydown', e => { if (e.key === 'Escape') clearSelection(); });
    document.body.addEventListener('htmx:afterSettle', syncSelection);
    // Capture before HTMX and Bootstrap handle task-title links.
    document.addEventListener('click', function(e) {
        const card = e.target.closest('#board-container .task-card');
        if (!card || !e.shiftKey || e.target.closest('select, input, button, textarea, label')) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        if (saving) return;
        const id = card.dataset.taskId;
        if (selectedIds.has(id)) selectedIds.delete(id);
        else selectedIds.add(id);
        syncSelection();
    }, true);

    const statusStyles = {
        planned: { border: '#6c757d', badge: 'bg-secondary' },
        active: { border: '#198754', badge: 'bg-success' },
        awaiting_feedback: { border: '#ffc107', badge: 'bg-warning text-dark' },
        completed: { border: '#0d6efd', badge: 'bg-primary' },
    };
    const statusBadgeClasses = 'bg-secondary bg-success bg-warning text-dark bg-primary';

    function applyCardStatus(card, status) {
        const style = statusStyles[status];
        if (!style) return;
        card.style.borderLeft = '3px solid ' + style.border;
        const select = card.querySelector('select[name="status"]');
        if (!select) return;
        select.value = status;
        select.className = select.className
            .split(/\s+/)
            .filter(cls => cls && !statusBadgeClasses.split(/\s+/).includes(cls))
            .join(' ');
        style.badge.split(/\s+/).forEach(cls => select.classList.add(cls));
    }

    document.addEventListener('focusin', function(e) {
        const select = e.target.closest('#board-container .task-card select[name="status"]');
        if (select) select.dataset.previousStatus = select.value;
    });

    // When multiple cards are selected, status changes apply to the whole selection.
    document.addEventListener('change', function(e) {
        const select = e.target.closest('#board-container .task-card select[name="status"]');
        if (!select) return;
        const card = select.closest('.task-card');
        if (!card || !selectedIds.has(card.dataset.taskId) || selectedIds.size < 2) return;

        e.preventDefault();
        e.stopImmediatePropagation();
        if (saving) {
            if (select.dataset.previousStatus) select.value = select.dataset.previousStatus;
            return;
        }

        const status = select.value;
        const cards = Array.from(board.querySelectorAll('.task-card')).filter(c => selectedIds.has(c.dataset.taskId));
        const snapshots = cards.map(c => {
            const statusSelect = c.querySelector('select[name="status"]');
            const previous = c === card
                ? (select.dataset.previousStatus || statusSelect?.value)
                : statusSelect?.value;
            return { card: c, status: previous };
        });
        const error = document.getElementById('board-move-error');
        error.hidden = true;
        saving = true;
        cards.forEach(c => applyCardStatus(c, status));

        fetch(@json(route('projects.board.tasks.status-batch', $project)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                task_ids: cards.map(c => Number(c.dataset.taskId)),
                status: status
            })
        })
        .then(response => {
            if (!response.ok) throw new Error('Status update failed');
            return response.json();
        })
        .then(data => {
            if (!data.success) throw new Error('Status update failed');
        })
        .catch(() => {
            snapshots.forEach(({ card: c, status: previous }) => {
                if (previous) applyCardStatus(c, previous);
            });
            error.textContent = 'Could not update status for the selected tasks. Please reload the board before trying again.';
            error.hidden = false;
        })
        .finally(() => { saving = false; });
    }, true);
    let lastDropColumn = null;
    let lastDropPosition = -1;

    function reindexColumnPositions(container) {
        if (!container) return;
        container.querySelectorAll('.task-card').forEach(function(card, index) {
            card.dataset.position = index;
        });
    }

    function ensureEmptyState(container) {
        if (!container) return;
        if (container.querySelectorAll('.task-card').length > 0) return;
        if (container.querySelector('.board-column-empty')) return;
        container.innerHTML = '<div class="text-muted text-center py-4 board-column-empty"><small>No tasks in this column</small></div>';
    }

    function syncMovedCardColumn(card, newColumnId) {
        card.dataset.columnId = newColumnId;
        card.querySelectorAll('input[name="column_id"]').forEach(function(input) {
            input.value = newColumnId;
        });
        card.querySelectorAll('input[name="old_column_id"]').forEach(function(input) {
            input.value = newColumnId;
        });
        const columnSelect = card.querySelector('select[name="column_id"]');
        if (columnSelect) {
            columnSelect.value = newColumnId;
            columnSelect.setAttribute('hx-target', '#board-column-' + newColumnId + '-tasks');
            if (typeof htmx !== 'undefined') {
                htmx.process(columnSelect);
            }
        }
    }

    // Create insertion indicator
    function createInsertionIndicator() {
        const indicator = document.createElement('div');
        indicator.className = 'insertion-indicator';
        indicator.style.cssText = `
            height: 2px;
            background-color: #007bff;
            margin: 4px 0;
            border-radius: 1px;
            pointer-events: none;
        `;
        return indicator;
    }

    // Show insertion indicator at position (skip DOM work if already there)
    function showInsertionIndicator(container, beforeElement) {
        if (placeholder && placeholder.parentNode === container) {
            if (beforeElement) {
                if (placeholder.nextSibling === beforeElement) return;
            } else if (!placeholder.nextSibling) {
                return;
            }
        }
        if (!placeholder) {
            placeholder = createInsertionIndicator();
        }
        if (beforeElement) {
            container.insertBefore(placeholder, beforeElement);
        } else {
            container.appendChild(placeholder);
        }
    }

    // Hide insertion indicator
    function hideInsertionIndicator() {
        if (placeholder) {
            placeholder.remove();
            placeholder = null;
        }
    }

    // Get drop position based on mouse Y coordinate
    function getDropPosition(container, clientY) {
        const taskCards = container.querySelectorAll('.task-card:not(.dragging)');
        const length = taskCards.length;
        if (length === 0) return 0;

        let low = 0;
        let high = length;
        while (low < high) {
            const mid = (low + high) >> 1;
            const rect = taskCards[mid].getBoundingClientRect();
            if (clientY < rect.top + rect.height / 2) {
                high = mid;
            } else {
                low = mid + 1;
            }
        }
        return low;
    }

    function cardAtPosition(container, position) {
        return container.querySelectorAll('.task-card:not(.dragging)')[position] || null;
    }

    // Drag start
    document.addEventListener('dragstart', function(e) {
        const card = e.target.closest('#board-container .task-card');
        if (!card) return;

        draggedTask = card;
        if (saving || !board.contains(draggedTask)) {
            e.preventDefault();
            draggedTask = null;
            return;
        }
        if (!selectedIds.has(draggedTask.dataset.taskId)) {
            if (!e.shiftKey) selectedIds.clear();
            selectedIds.add(draggedTask.dataset.taskId);
            syncSelection();
        }
        draggedTasks = Array.from(board.querySelectorAll('.task-selected'));
        lastDropColumn = null;
        lastDropPosition = -1;
        draggedTasks.forEach(card => {
            card.style.opacity = '0.5';
            card.classList.add('dragging');
        });

        // Set drag data
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', draggedTask.dataset.taskId);
    });

    // Drag over
    document.addEventListener('dragover', function(e) {
        if (!draggedTask) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';

        const column = e.target.closest('.board-column');
        if (!column) return;

        const taskContainer = column.querySelector('[id^="board-column-"][id$="-tasks"]');
        if (!taskContainer) return;

        const position = getDropPosition(taskContainer, e.clientY);
        if (column === lastDropColumn && position === lastDropPosition) {
            return;
        }
        lastDropColumn = column;
        lastDropPosition = position;

        if (!column.classList.contains('drop-target')) {
            document.querySelectorAll('.board-column.drop-target').forEach(function(col) {
                col.classList.remove('drop-target');
            });
            column.classList.add('drop-target');
        }

        showInsertionIndicator(taskContainer, cardAtPosition(taskContainer, position));
    });

    // Drag leave
    document.addEventListener('dragleave', function(e) {
        // Only hide if leaving the column entirely
        const column = e.target.closest('.board-column');
        const relatedColumn = e.relatedTarget ? e.relatedTarget.closest('.board-column') : null;
        if (column && !relatedColumn) {
            column.classList.remove('drop-target');
            hideInsertionIndicator();
            lastDropColumn = null;
            lastDropPosition = -1;
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
        const beforeElement = cardAtPosition(taskContainer, position);
        const moving = [...draggedTasks];
        const containers = new Set(moving.map(card => card.parentElement));
        containers.add(taskContainer);
        const snapshots = new Map([...containers].map(container => [container, Array.from(container.querySelectorAll('.task-card'))]));
        const originalColumns = new Map(moving.map(card => [card, card.dataset.columnId]));
        const error = document.getElementById('board-move-error');
        error.hidden = true;
        saving = true;

        taskContainer.querySelector('.board-column-empty')?.remove();
        moving.forEach(card => {
            taskContainer.insertBefore(card, beforeElement);
            syncMovedCardColumn(card, newColumnId);
        });
        resetDragState();
        containers.forEach(container => {
            reindexColumnPositions(container);
            ensureEmptyState(container);
            updateBoardColumnCount(container.closest('.board-column'));
        });

        fetch(@json(route('projects.board.tasks.move-batch', $project)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                task_ids: moving.map(card => Number(card.dataset.taskId)),
                column_id: Number(newColumnId),
                before_task_id: beforeElement ? Number(beforeElement.dataset.taskId) : null
            })
        })
        .then(response => {
            if (!response.ok) throw new Error('Move failed');
            return response.json();
        })
        .then(data => {
            if (!data.success) throw new Error('Move failed');
        })
        .catch(() => {
            snapshots.forEach((cards, container) => {
                container.querySelector('.board-column-empty')?.remove();
                cards.forEach(card => container.appendChild(card));
            });
            originalColumns.forEach((columnId, card) => syncMovedCardColumn(card, columnId));
            containers.forEach(container => {
                reindexColumnPositions(container);
                ensureEmptyState(container);
                updateBoardColumnCount(container.closest('.board-column'));
            });
            error.textContent = 'Could not save the move. Please reload the board before trying again.';
            error.hidden = false;
        })
        .finally(() => { saving = false; });
    });

    // Drag end
    document.addEventListener('dragend', function(e) {
        resetDragState();
    });

    function resetDragState() {
        draggedTasks.forEach(card => {
            card.style.opacity = '';
            card.classList.remove('dragging');
        });
        draggedTasks = [];
        document.querySelectorAll('.board-column').forEach(col => col.classList.remove('drop-target'));
        hideInsertionIndicator();
        draggedTask = null;
        lastDropColumn = null;
        lastDropPosition = -1;
    }
})();

// Highlight newly created task
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const highlightTaskId = urlParams.get('highlight');
    
    if (highlightTaskId) {
        // Wait for DOM to be fully loaded
        setTimeout(function() {
            const taskCard = document.querySelector(`[data-task-id="${highlightTaskId}"]`);
            if (taskCard) {
                const cardBody = taskCard.querySelector('.card-body');
                
                // Store original background colors
                const originalBg = taskCard.style.backgroundColor;
                const originalBodyBg = cardBody ? cardBody.style.backgroundColor : '';
                
                // Add highlight with info color
                taskCard.style.transition = 'box-shadow 0.3s ease, transform 0.3s ease, background-color 0.3s ease';
                taskCard.style.boxShadow = '0 0 0 4px rgba(13, 110, 253, 0.5)'; // Bootstrap info color
                taskCard.style.transform = 'scale(1.03)';
                taskCard.style.backgroundColor = 'rgba(13, 110, 253, 0.15)'; // Light info background
                
                if (cardBody) {
                    cardBody.style.transition = 'background-color 0.3s ease';
                    cardBody.style.backgroundColor = 'rgba(13, 110, 253, 0.1)'; // Subtle body tint
                }
                
                // Scroll to the task card
                taskCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Fade out after 1 second
                setTimeout(function() {
                    taskCard.style.transition = 'box-shadow 1s ease, transform 1s ease, background-color 1s ease';
                    taskCard.style.boxShadow = '';
                    taskCard.style.transform = '';
                    taskCard.style.backgroundColor = originalBg;
                    
                    if (cardBody) {
                        cardBody.style.transition = 'background-color 1s ease';
                        cardBody.style.backgroundColor = originalBodyBg;
                    }
                    
                    // Clean up the URL parameter after highlight starts fading
                    const url = new URL(window.location);
                    url.searchParams.delete('highlight');
                    window.history.replaceState({}, '', url);
                }, 1000);
            }
        }, 100);
    }
})();
</script>

@if(session('open_task_id'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var editUrl = '{{ route('projects.board.tasks.edit', [$project, session('open_task_id', 0)]) }}';
    var boardUrl = '{{ route('projects.board', $project) }}';
    var modalEl = document.getElementById('taskModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
    history.replaceState(null, '', editUrl);
    modalEl.addEventListener('shown.bs.modal', function () {
        htmx.ajax('GET', editUrl, {
            target: '#taskModalContent',
            swap: 'innerHTML'
        });
    }, { once: true });
    modalEl.addEventListener('hidden.bs.modal', function () {
        history.replaceState(null, '', boardUrl);
    }, { once: true });
});
</script>
@endif
@endsection
