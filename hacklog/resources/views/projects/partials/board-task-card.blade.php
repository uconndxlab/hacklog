{{-- Task card for project board view --}}
<div class="card mb-2 task-card shadow-sm" 
     data-task-id="{{ $task->id }}" 
     data-column-id="{{ $task->column_id }}" 
     data-position="{{ $task->position }}"
     draggable="true"
     style="cursor: grab; border-left: 3px solid 
        @if($task->status === 'planned') #6c757d
        @elseif($task->status === 'active') #198754
        @elseif($task->status === 'awaiting_feedback') #ffc107
        @else #0d6efd
        @endif;">
    
    {{-- Card Header with Assignment and Status --}}
    <div class="card-header bg-light py-1 px-2 d-flex justify-content-between align-items-center" style="border-bottom: 1px solid rgba(0,0,0,.125);">
        {{-- Assignment Info --}}
        <div style="flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-right: 8px;">
            @if($task->users->isNotEmpty())
                <small class="text-muted" style="font-size: 0.7rem;" title="{{ $task->users->pluck('name')->join(', ') }}">
                    {{ $task->users->pluck('name')->join(', ') }}
                </small>
            @else
                <small class="text-muted" style="font-size: 0.7rem;">Unassigned</small>
            @endif
        </div>
        
        {{-- Interactive status dropdown --}}
        <form 
            action="{{ route('projects.board.tasks.update', [$project, $task]) }}" 
            method="POST"
            class="d-inline">
            @csrf
            @method('PUT')
            <input type="hidden" name="from_board_modal" value="1">
            <input type="hidden" name="status_change_only" value="1">
            @if(isset($filterPhaseId) && $filterPhaseId)
                <input type="hidden" name="filter_phase_id" value="{{ $filterPhaseId }}">
            @endif
            @if(isset($filterAssigned) && $filterAssigned)
                <input type="hidden" name="filter_assigned" value="{{ $filterAssigned }}">
            @endif
            
            <select 
                name="status" 
                class="form-select form-select-sm d-inline-block w-auto badge 
                @if($task->status === 'planned') bg-secondary
                @elseif($task->status === 'active') bg-success
                @elseif($task->status === 'awaiting_feedback') bg-warning text-dark
                @else bg-primary
                @endif"
                style="cursor: pointer; text-align: left; border: none; padding-right: 1.5rem; font-size: 0.65rem; white-space: nowrap;"
                hx-trigger="change"
                hx-put="{{ route('projects.board.tasks.update', [$project, $task]) }}"
                hx-target="[data-task-id='{{ $task->id }}']"
                hx-swap="outerHTML"
                hx-include="closest form"
                title="Click to change status">
                <option value="planned" {{ $task->status === 'planned' ? 'selected' : '' }}>Planned</option>
                <option value="active" {{ $task->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="awaiting_feedback" {{ $task->status === 'awaiting_feedback' ? 'selected' : '' }}>Awaiting Feedback</option>
                <option value="completed" {{ $task->status === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
        </form>
    </div>
    
    <div class="card-body p-3">
        {{-- Title (Full Width) --}}
        <h6 class="card-title mb-2">
            <a href="{{ route('projects.board.tasks.edit', [$project, $task]) }}" 
               class="text-decoration-none fw-semibold"
               data-bs-toggle="modal" 
               data-bs-target="#taskModal"
               hx-get="{{ route('projects.board.tasks.edit', [$project, $task]) }}"
               hx-target="#taskModalContent"
               hx-push-url="{{ route('projects.board.tasks.edit', [$project, $task]) }}"
               style="display: block;">
                <span class="badge bg-secondary text-white me-1" style="font-size: 0.65rem; font-weight: normal;">#{{ $task->id }}</span>
                {{ $task->title }}
            </a>
        </h6>

        {{-- Key Information - Compact Layout --}}
        <div class="mb-2">
            {{-- Phase Badge --}}
            @if($task->phase)
                <span class="badge bg-light text-dark border me-1 mb-1" style="font-size: 0.7rem; font-weight: normal;">
                    {{ $task->phase->name }}
                </span>
            @endif
            
            {{-- Due Date Badge --}}
            @if($task->due_date)
                <span class="badge {{ $task->isOverdue() ? 'bg-danger' : 'bg-light text-dark border' }} me-1 mb-1" 
                      style="font-size: 0.7rem; font-weight: normal;">
                    @if($task->isOverdue())
                        Overdue: {{ $task->due_date->format('M j') }}
                    @else
                        Due: {{ $task->due_date->format('M j') }}
                    @endif
                </span>
            @endif

            {{-- Priority chip (inline editable) --}}
            <form class="d-inline"
                  hx-put="{{ route('projects.board.tasks.update', [$project, $task]) }}"
                  hx-target="[data-task-id='{{ $task->id }}']"
                  hx-swap="outerHTML"
                  hx-trigger="change from:select[name='priority']">
                @csrf
                @method('PUT')
                <input type="hidden" name="from_board_modal" value="1">
                <input type="hidden" name="priority_change_only" value="1">
                @if(isset($filterPhaseId) && $filterPhaseId)
                    <input type="hidden" name="filter_phase_id" value="{{ $filterPhaseId }}">
                @endif
                @if(isset($filterAssigned) && $filterAssigned)
                    <input type="hidden" name="filter_assigned" value="{{ $filterAssigned }}">
                @endif
                <select name="priority"
                        class="border-0 bg-transparent p-0 me-1 mb-1"
                        style="font-size: 0.7rem; cursor: pointer; appearance: none; -webkit-appearance: none; outline: none;
                               color: {{ $task->priority === 'high' ? '#dc3545' : ($task->priority === 'medium' ? '#fd7e14' : ($task->priority === 'low' ? '#6c757d' : '#adb5bd')) }};"
                        title="Priority">
                    <option value="">— priority</option>
                    <option value="high"   {{ $task->priority === 'high'   ? 'selected' : '' }} style="color:#dc3545;">↑ High</option>
                    <option value="medium" {{ $task->priority === 'medium' ? 'selected' : '' }} style="color:#fd7e14;">~ Medium</option>
                    <option value="low"    {{ $task->priority === 'low'    ? 'selected' : '' }} style="color:#6c757d;">↓ Low</option>
                </select>
            </form>

            {{-- Weight chip (inline editable) --}}
            <form class="d-inline"
                  hx-put="{{ route('projects.board.tasks.update', [$project, $task]) }}"
                  hx-target="[data-task-id='{{ $task->id }}']"
                  hx-swap="outerHTML"
                  hx-trigger="change from:select[name='weight']">
                @csrf
                @method('PUT')
                <input type="hidden" name="from_board_modal" value="1">
                <input type="hidden" name="weight_change_only" value="1">
                @if(isset($filterPhaseId) && $filterPhaseId)
                    <input type="hidden" name="filter_phase_id" value="{{ $filterPhaseId }}">
                @endif
                @if(isset($filterAssigned) && $filterAssigned)
                    <input type="hidden" name="filter_assigned" value="{{ $filterAssigned }}">
                @endif
                <select name="weight"
                        class="border-0 bg-transparent p-0 me-1 mb-1"
                        style="font-size: 0.7rem; cursor: pointer; appearance: none; -webkit-appearance: none; outline: none; color: #495057; font-family: monospace;"
                        title="Effort weight">
                    <option value="">— weight</option>
                    <option value="xs" {{ $task->weight === 'xs' ? 'selected' : '' }}>XS</option>
                    <option value="s"  {{ $task->weight === 's'  ? 'selected' : '' }}>S</option>
                    <option value="m"  {{ $task->weight === 'm'  ? 'selected' : '' }}>M</option>
                    <option value="l"  {{ $task->weight === 'l'  ? 'selected' : '' }}>L</option>
                    <option value="xl" {{ $task->weight === 'xl' ? 'selected' : '' }}>XL</option>
                </select>
            </form>
        </div>

        {{-- Last Updated (subtle) --}}
        <div class="text-muted" style="font-size: 0.65rem;">
            Updated {{ $task->updated_at->diffForHumans() }}@if($task->creator) • Created by {{ $task->creator->name }}@endif
        </div>

        {{-- Column selector with HTMX enhancement --}}
        <form 
            action="{{ route('projects.board.tasks.update', [$project, $task]) }}" 
            method="POST"
            class="mt-2">
            @csrf
            @method('PUT')
            <input type="hidden" name="from_board_modal" value="1">
            <input type="hidden" name="column_change_only" value="1">
            @if(isset($filterPhaseId) && $filterPhaseId)
                <input type="hidden" name="filter_phase_id" value="{{ $filterPhaseId }}">
            @endif
            @if(isset($filterAssigned) && $filterAssigned)
                <input type="hidden" name="filter_assigned" value="{{ $filterAssigned }}">
            @endif
            
            <div class="input-group input-group-sm">
                <label class="input-group-text" for="columnSelect-{{ $task->id }}" style="font-size: 0.7rem;">Move to:</label>
                <select 
                    name="column_id" 
                    class="form-select form-select-sm"
                    style="font-size: 0.75rem;"
                    hx-trigger="change"
                    hx-put="{{ route('projects.board.tasks.update', [$project, $task]) }}"
                    hx-target="#board-column-{{ $task->column_id }}-tasks"
                    hx-swap="outerHTML"
                    hx-include="closest form">
                    @foreach($allColumns as $col)
                        <option value="{{ $col->id }}" {{ $task->column_id == $col->id ? 'selected' : '' }}>
                            {{ $col->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>

        
    </div>
</div>
