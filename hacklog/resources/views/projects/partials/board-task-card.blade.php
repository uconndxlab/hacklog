{{-- Task card for project board view --}}
<div class="card mb-2" id="board-task-{{ $task->id }}">
    <div class="card-body p-2">
        <h6 class="card-title mb-1">
            <a href="{{ route('projects.epics.tasks.show', [$project, $task->epic, $task]) }}" 
               class="text-decoration-none"
               data-bs-toggle="modal" 
               data-bs-target="#taskDetailsModal"
               hx-get="{{ route('projects.board.tasks.show', [$project, $task]) }}"
               hx-target="#taskDetailsModalContent">
                {{ $task->title }}
            </a>
        </h6>
        <p class="card-text mb-2">
            <small class="text-muted">Epic: {{ $task->epic->name }}</small>
        </p>
        @if($task->start_date || $task->due_date)
            <p class="card-text mb-2">
                <small class="text-muted">
                    @if($task->start_date && $task->due_date)
                        {{ $task->start_date->format('M j') }} → {{ $task->due_date->format('M j, Y') }}
                    @elseif($task->start_date)
                        Starts: {{ $task->start_date->format('M j, Y') }}
                    @else
                        Due: {{ $task->due_date->format('M j, Y') }}
                    @endif
                </small>
            </p>
        @endif
        @if($task->users->isNotEmpty())
            <p class="card-text mb-2">
                <small class="text-muted">
                    Assigned: {{ $task->users->pluck('name')->join(', ') }}
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
        
        {{-- Move up/down buttons --}}
        <div class="d-flex gap-1 mb-2">
            @if($task->canMoveUp())
                <form 
                    action="{{ route('projects.epics.tasks.move-up', [$project, $task->epic, $task]) }}" 
                    method="POST" 
                    class="d-inline"
                    hx-post="{{ route('projects.epics.tasks.move-up', [$project, $task->epic, $task]) }}"
                    hx-target="#board-column-{{ $task->column_id }}-tasks"
                    hx-swap="outerHTML">
                    @csrf
                    <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Move Up</button>
                </form>
            @endif

            @if($task->canMoveDown())
                <form 
                    action="{{ route('projects.epics.tasks.move-down', [$project, $task->epic, $task]) }}" 
                    method="POST" 
                    class="d-inline"
                    hx-post="{{ route('projects.epics.tasks.move-down', [$project, $task->epic, $task]) }}"
                    hx-target="#board-column-{{ $task->column_id }}-tasks"
                    hx-swap="outerHTML">
                    @csrf
                    <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Move Down</button>
                </form>
            @endif
        </div>
        
        {{-- Column selector with HTMX enhancement --}}
        <form 
            action="{{ route('projects.epics.tasks.update', [$project, $task->epic, $task]) }}" 
            method="POST"
            hx-put="{{ route('projects.epics.tasks.update', [$project, $task->epic, $task]) }}"
            hx-target="#board-column-{{ $task->column_id }}-tasks"
            hx-swap="outerHTML"
            hx-include="[name='_token'], [name='column_id']"
            class="mt-2">
            @csrf
            @method('PUT')
            
            {{-- Hidden fields to pass through required data --}}
            <input type="hidden" name="title" value="{{ $task->title }}">
            <input type="hidden" name="description" value="{{ $task->description }}">
            <input type="hidden" name="status" value="{{ $task->status }}">
            <input type="hidden" name="from_board" value="{{ isset($isProjectBoard) && $isProjectBoard ? '1' : '0' }}">
            <input type="hidden" name="old_column_id" value="{{ $task->column_id }}">
            
            <div class="input-group input-group-sm">
                <select 
                    name="column_id" 
                    class="form-select form-select-sm"
                    onchange="this.form.submit()">
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
