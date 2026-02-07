{{-- Column task list for board view (supports out-of-band swaps) --}}
<div 
    id="board-column-{{ $column->id }}-tasks" 
    class="card-body p-2"
    @if(isset($isOob) && $isOob) hx-swap-oob="true" @endif>
    @forelse($columnTasks as $task)
        @include('projects.partials.board-task-card', [
            'project' => $project,
            'task' => $task,
            'allColumns' => $allColumns,
            'isProjectBoard' => $isProjectBoard ?? true
        ])
    @empty
        <div class="text-muted text-center py-4">
            <small>No tasks in this column</small>
        </div>
    @endforelse
</div>
