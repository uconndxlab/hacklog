<div class="card mb-3" id="task-{{ $task->id }}">
    <div class="card-body">
        <h6 class="card-title mb-2">
            <a href="{{ route('projects.epics.tasks.show', [$project, $epic, $task]) }}">
                {{ $task->title }}
            </a>
        </h6>
        <p class="card-text mb-2">
            <span class="badge 
                @if($task->status === 'planned') bg-secondary
                @elseif($task->status === 'active') bg-success
                @else bg-primary
                @endif">
                {{ ucfirst($task->status) }}
            </span>
        </p>
        <div class="d-flex gap-1 flex-wrap">
            <a href="{{ route('projects.epics.tasks.edit', [$project, $epic, $task]) }}" 
               class="btn btn-sm btn-outline-secondary">Edit</a>
            
            @if($task->canMoveUp())
                <form 
                    action="{{ route('projects.epics.tasks.move-up', [$project, $epic, $task]) }}" 
                    method="POST" 
                    class="d-inline"
                    hx-post="{{ route('projects.epics.tasks.move-up', [$project, $epic, $task]) }}"
                    hx-target="#column-{{ $task->column_id }}-tasks"
                    hx-swap="outerHTML">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">Move Up</button>
                </form>
            @endif

            @if($task->canMoveDown())
                <form 
                    action="{{ route('projects.epics.tasks.move-down', [$project, $epic, $task]) }}" 
                    method="POST" 
                    class="d-inline"
                    hx-post="{{ route('projects.epics.tasks.move-down', [$project, $epic, $task]) }}"
                    hx-target="#column-{{ $task->column_id }}-tasks"
                    hx-swap="outerHTML">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-outline-primary">Move Down</button>
                </form>
            @endif

            <form 
                action="{{ route('projects.epics.tasks.destroy', [$project, $epic, $task]) }}" 
                method="POST" 
                class="d-inline"
                hx-delete="{{ route('projects.epics.tasks.destroy', [$project, $epic, $task]) }}"
                hx-target="#task-{{ $task->id }}"
                hx-swap="outerHTML"
                hx-confirm="Are you sure you want to delete this task?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
        </div>
    </div>
</div>
