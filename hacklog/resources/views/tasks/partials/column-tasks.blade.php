{{-- Single column's task list (used for HTMX swaps) --}}
<div class="card-body" id="column-{{ $column->id }}-tasks">
    @if($columnTasks->isEmpty())
        <p class="text-muted text-center py-3 mb-0">No tasks</p>
    @else
        @foreach($columnTasks as $task)
            @include('tasks.partials.task-card', ['project' => $project, 'epic' => $epic, 'task' => $task])
        @endforeach
    @endif
</div>
