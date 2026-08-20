{{--
Compact Task Card for info form

@param \Illuminate\Support\Collection $tasks
@param array $selectedTaskIds
@param boolean $isEdit
--}}

@php
    $selectedTasks = $tasks
        ->whereIn('id', $selectedTaskIds)
        ->map(fn ($task) => [
            'name' => $task->title,
            'project_name' => $task->column->project->name,
            'task_id' => $task->id,
            'project_id' => $task->column->project->id,
        ])
        ->values()
        ->all();
    @endphp
@if(is_array($selectedTasks) && count($selectedTasks) > 0)
    <div style="width: 100%; display: flex; gap: 3px; flex-wrap: wrap;">

        @foreach ($selectedTasks as $taskObject)

            @php
                $taskUrl = '/projects/' . $taskObject['project_id'] . '/board/tasks/' . $taskObject['task_id'] . '/edit';
                @endphp

            <a
                class="badge bg-light text-dark border d-flex p-1"
                href="{{$taskUrl}}"
                style="flex-direction: column; align-items: flex-start; gap: 2px; max-width: 250px; overflow: hidden"
            >
                <p style="font-weight: bold; padding: 0; margin: 0">{{ $taskObject['name'] }}</p>
                <span class="text-muted">{{ $taskObject['project_name'] }}</span>
            </a>
            @endforeach

    </div>
@endif
