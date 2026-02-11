{{-- Kanban column for project board view --}}
<div class="card h-100 board-column" data-column-id="{{ $column->id }}">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">{{ $column->name }}</h5>
            <small class="text-muted">{{ $columnTasks->count() }} {{ Str::plural('task', $columnTasks->count()) }}</small>
        </div>
        @if(isset($isProjectBoard) && $isProjectBoard)
            <button 
                type="button" 
                class="btn btn-sm btn-outline-primary"
                data-bs-toggle="modal" 
                data-bs-target="#taskModal"
                hx-get="{{ route('projects.board.task-form', array_merge(['project' => $project, 'column' => $column->id], request()->has('phase') ? ['phase' => request('phase')] : [])) }}"
                hx-target="#taskModalContent"
                hx-swap="innerHTML">
                Add task
            </button>
        @endif
    </div>
    @include('projects.partials.board-column-tasks', [
        'column' => $column,
        'columnTasks' => $columnTasks,
        'project' => $project,
        'allColumns' => $allColumns,
        'isProjectBoard' => $isProjectBoard ?? true,
        'filterPhaseId' => $filterPhaseId ?? null
    ])
</div>
