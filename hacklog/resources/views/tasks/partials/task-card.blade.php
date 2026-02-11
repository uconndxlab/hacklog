<div class="card mb-3" id="task-{{ $task->id }}">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <h6 class="card-title mb-0">
                <a href="{{ route('projects.phases.tasks.show', [$project, $phase, $task]) }}">
                    {{ $task->title }}
                </a>
            </h6>
            
            {{-- Move up/down buttons --}}
            @if($task->canMoveUp($filterPhaseId ?? null) || $task->canMoveDown($filterPhaseId ?? null))
                <div class="btn-group btn-group-sm" role="group">
                    @if($task->canMoveUp($filterPhaseId ?? null))
                        <form action="{{ route('projects.phases.tasks.move-up', [$project, $phase, $task]) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary">↑</button>
                        </form>
                    @else
                        <button type="button" class="btn btn-outline-secondary" disabled>↑</button>
                    @endif
                    
                    @if($task->canMoveDown($filterPhaseId ?? null))
                        <form action="{{ route('projects.phases.tasks.move-down', [$project, $phase, $task]) }}" method="POST" class="d-inline">
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
            <a href="{{ route('projects.phases.tasks.edit', [$project, $phase, $task]) }}" 
               class="btn btn-sm btn-outline-secondary">Edit</a>
            
            <form 
                action="{{ route('projects.phases.tasks.destroy', [$project, $phase, $task]) }}" 
                method="POST" 
                class="d-inline"
                hx-delete="{{ route('projects.phases.tasks.destroy', [$project, $phase, $task]) }}"
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
