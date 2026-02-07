<div class="card mb-3" id="task-{{ $task->id }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="card-title mb-0">
                <a href="{{ route('projects.epics.tasks.show', [$project, $epic, $task]) }}">
                    {{ $task->title }}
                </a>
            </h6>
            
            {{-- Move up/down buttons --}}
            @if($task->canMoveUp() || $task->canMoveDown())
                <div class="btn-group btn-group-sm" role="group">
                    @if($task->canMoveUp())
                        <form action="{{ route('projects.epics.tasks.move-up', [$project, $epic, $task]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">↑</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>↑</button>
                    @endif
                    
                    @if($task->canMoveDown())
                        <form action="{{ route('projects.epics.tasks.move-down', [$project, $epic, $task]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">↓</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>↓</button>
                    @endif
                </div>
            @endif
        </div>
        
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
