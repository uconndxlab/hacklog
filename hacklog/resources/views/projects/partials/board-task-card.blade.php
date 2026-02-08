{{-- Task card for project board view --}}
<div class="card mb-2" id="board-task-{{ $task->id }}">
    <div class="card-body p-2">
        <div class="d-flex justify-content-between align-items-start mb-1">
            <h6 class="card-title mb-0">
                <a href="{{ route('projects.phases.tasks.show', [$project, $task->phase, $task]) }}" 
                   class="text-decoration-none"
                   data-bs-toggle="modal" 
                   data-bs-target="#taskDetailsModal"
                   hx-get="{{ route('projects.board.tasks.show', [$project, $task]) }}"
                   hx-target="#taskDetailsModalContent">
                    {{ $task->title }}
                </a>
            </h6>
            
            {{-- Move up/down buttons --}}
            @if($task->canMoveUp() || $task->canMoveDown())
                <div class="btn-group btn-group-sm" role="group">
                    @if($task->canMoveUp())
                        <form 
                            action="{{ route('projects.phases.tasks.move-up', [$project, $task->phase, $task]) }}" 
                            method="POST" 
                            class="d-inline"
                            hx-post="{{ route('projects.phases.tasks.move-up', [$project, $task->phase, $task]) }}"
                            hx-target="#board-column-{{ $task->column_id }}-tasks"
                            hx-swap="outerHTML">
                            @csrf
                            <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
                            <button type="submit" class="btn btn-outline-secondary">↑</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>↑</button>
                    @endif
                    
                    @if($task->canMoveDown())
                        <form 
                            action="{{ route('projects.phases.tasks.move-down', [$project, $task->phase, $task]) }}" 
                            method="POST" 
                            class="d-inline"
                            hx-post="{{ route('projects.phases.tasks.move-down', [$project, $task->phase, $task]) }}"
                            hx-target="#board-column-{{ $task->column_id }}-tasks"
                            hx-swap="outerHTML">
                            @csrf
                            <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
                            <button type="submit" class="btn btn-outline-secondary">↓</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>↓</button>
                    @endif
                </div>
            @endif
        </div>
        
        <p class="card-text mb-2">
            <small class="text-muted">Phase: {{ $task->phase->name }}</small>
        </p>
        
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
        
        <p class="card-text mb-2">
            <span class="badge 
                @if($task->status === 'planned') bg-secondary
                @elseif($task->status === 'active') bg-success
                @else bg-primary
                @endif">
                {{ ucfirst($task->status) }}
            </span>
        </p>
        
        {{-- Edit button --}}
        <div class="mb-2">
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
            action="{{ route('projects.phases.tasks.update', [$project, $task->phase, $task]) }}" 
            method="POST"
            class="mt-2">
            @csrf
            @method('PUT')
            
            {{-- Hidden fields to pass through required data --}}
            <input type="hidden" name="title" value="{{ $task->title }}">
            <input type="hidden" name="description" value="{{ $task->description }}">
            <input type="hidden" name="status" value="{{ $task->status }}">
            <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
            <input type="hidden" name="old_column_id" value="{{ $task->column_id }}">
            <input type="hidden" name="column_change_only" value="1">
            @foreach($task->users as $user)
                <input type="hidden" name="assignees[]" value="{{ $user->id }}">
            @endforeach
            
            <div class="input-group input-group-sm">
                <select 
                    name="column_id" 
                    class="form-select form-select-sm"
                    hx-trigger="change"
                    hx-put="{{ route('projects.phases.tasks.update', [$project, $task->phase, $task]) }}"
                    hx-target="#board-column-{{ $task->column_id }}-tasks"
                    hx-swap="outerHTML"
                    hx-include="closest form">
                    @foreach($allColumns as $col)
                        <option value="{{ $col->id }}" {{ $task->column_id == $col->id ? 'selected' : '' }}>
                            {{ $col->name }}
                        </option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-secondary btn-sm">Move</button>
            </div>
        </form>
    </div>
</div>
