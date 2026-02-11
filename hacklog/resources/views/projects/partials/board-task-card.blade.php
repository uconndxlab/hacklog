{{-- Task card for project board view --}}
<div class="card mb-2 task-card" 
     data-task-id="{{ $task->id }}" 
     data-column-id="{{ $task->column_id }}" 
     data-position="{{ $task->position }}"
     draggable="true">
    <div class="card-body p-2">
        <div class="d-flex justify-content-between align-items-start mb-1">
            <h6 class="card-title mb-0">
                <a href="{{ route('projects.board.tasks.edit', [$project, $task]) }}" 
                   class="text-decoration-none"
                   data-bs-toggle="modal" 
                   data-bs-target="#taskModal"
                   hx-get="{{ route('projects.board.tasks.edit', [$project, $task]) }}"
                   hx-target="#taskModalContent">
                    {{ $task->title }}
                </a>
                {{-- Interactive status badge --}}
        <div class="my-2">
            <form 
                action="{{ route('projects.board.tasks.update', [$project, $task]) }}" 
                method="POST"
                class="d-inline">
                @csrf
                @method('PUT')
                
                {{-- Hidden fields to preserve task data --}}
                <input type="hidden" name="title" value="{{ $task->title }}">
                <input type="hidden" name="description" value="{{ $task->description }}">
                <input type="hidden" name="phase_id" value="{{ $task->phase_id }}">
                <input type="hidden" name="column_id" value="{{ $task->column_id }}">
                <input type="hidden" name="from_board_modal" value="1">
                <input type="hidden" name="status_change_only" value="1">
                @if($task->start_date)
                    <input type="hidden" name="start_date" value="{{ $task->start_date->format('Y-m-d') }}">
                @endif
                @if($task->due_date)
                    <input type="hidden" name="due_date" value="{{ $task->due_date->format('Y-m-d') }}">
                @endif
                @foreach($task->users as $user)
                    <input type="hidden" name="assignees[]" value="{{ $user->id }}">
                @endforeach
                
                <select 
                    name="status" 
                    class="form-select form-select-sm d-inline-block w-auto badge 
                    @if($task->status === 'planned') bg-secondary
                    @elseif($task->status === 'active') bg-success
                    @else bg-primary
                    @endif"
                    style="cursor: pointer; text-align: left; border: none; padding-right: 1.5rem;"
                    hx-trigger="change"
                    hx-put="{{ route('projects.board.tasks.update', [$project, $task]) }}"
                    hx-target="[data-task-id='{{ $task->id }}']"
                    hx-swap="outerHTML"
                    hx-include="closest form"
                    title="Click to change status">
                    <option value="planned" {{ $task->status === 'planned' ? 'selected' : '' }}>Planned</option>
                    <option value="active" {{ $task->status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="completed" {{ $task->status === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
            </form>
        </div>
            </h6>
            
            {{-- Move up/down buttons --}}
            @if($task->canMoveUp($filterPhaseId ?? null) || $task->canMoveDown($filterPhaseId ?? null))
                <div class="btn-group btn-group-sm" role="group">
                    @if($task->canMoveUp($filterPhaseId ?? null))
                        <form 
                            action="{{ $task->phase ? route('projects.phases.tasks.move-up', [$project, $task->phase, $task]) : '#' }}" 
                            method="POST" 
                            class="d-inline"
                            hx-post="{{ $task->phase ? route('projects.phases.tasks.move-up', [$project, $task->phase, $task]) : '#' }}"
                            hx-target="#board-column-{{ $task->column_id }}-tasks"
                            hx-swap="outerHTML">
                            @csrf
                            <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
                            @if(isset($filterPhaseId) && $filterPhaseId)
                                <input type="hidden" name="filter_phase_id" value="{{ $filterPhaseId }}">
                            @endif
                            @if(request('assigned'))
                                <input type="hidden" name="filter_assigned" value="{{ request('assigned') }}">
                            @endif
                            <button type="submit" class="btn btn-outline-secondary">↑</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>↑</button>
                    @endif
                    
                    @if($task->canMoveDown($filterPhaseId ?? null))
                        <form 
                            action="{{ $task->phase ? route('projects.phases.tasks.move-down', [$project, $task->phase, $task]) : '#' }}" 
                            method="POST" 
                            class="d-inline"
                            hx-post="{{ $task->phase ? route('projects.phases.tasks.move-down', [$project, $task->phase, $task]) : '#' }}"
                            hx-target="#board-column-{{ $task->column_id }}-tasks"
                            hx-swap="outerHTML">
                            @csrf
                            <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
                            @if(isset($filterPhaseId) && $filterPhaseId)
                                <input type="hidden" name="filter_phase_id" value="{{ $filterPhaseId }}">
                            @endif
                            @if(request('assigned'))
                                <input type="hidden" name="filter_assigned" value="{{ request('assigned') }}">
                            @endif
                            <button type="submit" class="btn btn-outline-secondary">↓</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>↑</button>
                    @endif
                </div>
            @endif
        </div>

        
        
        @if($task->phase)
            <p class="card-text mb-2">
                <small class="text-muted">Phase: {{ $task->phase->name }}</small>
            </p>
        @endif

        
        
        @if($task->due_date)
            <p class="card-text mb-2">
                <small class="text-muted">
                    <strong>Due:</strong> {{ $task->due_date->format('M j, Y') }}
                    @if($task->isOverdue())
                        <span class="badge bg-danger ms-1">Overdue</span>
                    @endif
                </small>
            </p>
        @endif
        
        @if($task->users->isNotEmpty())
            <p class="card-text mb-2">
                <small class="text-muted">
                    <strong>Assigned:</strong> {{ $task->users->pluck('name')->join(', ') }}
                </small>
            </p>
        @endif
        
    
        
        {{-- Edit button --}}
        <div class="mb-2 d-none">
            <button 
                type="button" 
                class="btn btn-sm btn-outline-secondary w-100"
                data-bs-toggle="modal" 
                data-bs-target="#taskModal"
                hx-get="{{ route('projects.board.tasks.edit', [$project, $task]) }}"
                hx-target="#taskModalContent">
                Edit Task
            </button>
        </div>
        
        {{-- Column selector with HTMX enhancement --}}
        <form 
            action="{{ route('projects.board.tasks.update', [$project, $task]) }}" 
            method="POST"
            class="mt-2">
            @csrf
            @method('PUT')
            
            {{-- Hidden fields to pass through required data --}}
            <input type="hidden" name="title" value="{{ $task->title }}">
            <input type="hidden" name="description" value="{{ $task->description }}">
            <input type="hidden" name="status" value="{{ $task->status }}">
            <input type="hidden" name="phase_id" value="{{ $task->phase_id }}">
            <input type="hidden" name="from_board_modal" value="1">
            <input type="hidden" name="old_column_id" value="{{ $task->column_id }}">
            <input type="hidden" name="column_change_only" value="1">
            @if(isset($filterPhaseId) && $filterPhaseId)
                <input type="hidden" name="filter_phase_id" value="{{ $filterPhaseId }}">
            @endif
            @if(request('assigned'))
                <input type="hidden" name="filter_assigned" value="{{ request('assigned') }}">
            @endif
            @foreach($task->users as $user)
                <input type="hidden" name="assignees[]" value="{{ $user->id }}">
            @endforeach
            
            <div class="input-group input-group-sm">
                <label class="input-group-text" for="columnSelect-{{ $task->id }}">Move to:</label>
                <select 
                    name="column_id" 
                    class="form-select form-select-sm"
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
                <button type="submit" class="btn btn-outline-secondary btn-sm d-none">Move</button>
            </div>
        </form>

        
    </div>
</div>
