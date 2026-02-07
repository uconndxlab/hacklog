{{-- Kanban Board: All columns with their tasks --}}
<div id="kanban-board" class="row">
    @foreach($columns as $column)
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">{{ $column->name }}</h5>
                </div>
                @php
                    $columnTasks = $epic->tasks->where('column_id', $column->id);
                @endphp
                @include('tasks.partials.column-tasks', ['project' => $project, 'epic' => $epic, 'column' => $column, 'columnTasks' => $columnTasks])
            </div>
        </div>
    @endforeach
</div>
