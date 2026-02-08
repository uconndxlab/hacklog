{{-- Task creation/edit form for board modal --}}
@php
    $isEdit = isset($task);
    $formAction = $isEdit 
        ? route('projects.board.tasks.update', [$project, $task])
        : route('projects.board.tasks.store', $project);
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $htmxMethod = $isEdit ? 'hx-put' : 'hx-post';
    $targetColumnId = $isEdit ? $task->column_id : $columnId;
@endphp

<form 
    action="{{ $formAction }}" 
    method="POST"
    {{ $htmxMethod }}="{{ $formAction }}"
    hx-target="#board-column-{{ $targetColumnId }}-tasks"
    hx-swap="outerHTML">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif
    
    <input type="hidden" name="column_id" value="{{ $isEdit ? $task->column_id : $columnId }}">
    <input type="hidden" name="from_board_modal" value="1">
    
    <div class="mb-3">
        <label for="phase_id" class="form-label">Phase</label>
        <select
            class="form-select @error('phase_id') is-invalid @enderror"
            id="phase_id"
            name="phase_id"
            required>
            <option value="">Choose a phase...</option>
            @php
                $defaultPhaseId = old('phase_id', $isEdit ? $task->phase_id : ($phases->where('status', 'active')->first()?->id ?? $phases->first()?->id));
            @endphp
            @foreach($phases as $phase)
                <option value="{{ $phase->id }}"
                    {{ $defaultPhaseId == $phase->id ? 'selected' : '' }}>
                    {{ $phase->name }}
                    @if($phase->status === 'active')
                        (Active)
                    @elseif($phase->status === 'planned')
                        (Planned)
                    @elseif($phase->status === 'completed')
                        (Completed)
                    @endif
                </option>
            @endforeach
        </select>
        @error('phase_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
    <div class="mb-3">
        <label for="title" class="form-label">Task Title</label>
        <input 
            type="text" 
            class="form-control @error('title') is-invalid @enderror" 
            id="title" 
            name="title" 
            value="{{ old('title', $isEdit ? $task->title : '') }}" 
            required
            autofocus>
        @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">Description</label>
        <input 
            id="description" 
            type="hidden" 
            name="description" 
            value="{{ old('description', $isEdit ? $task->description : '') }}">
        <trix-editor input="description" class="@error('description') is-invalid @enderror"></trix-editor>
        @error('description')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="mb-3">
        <label for="status" class="form-label">Status</label>
        <select 
            class="form-select @error('status') is-invalid @enderror" 
            id="status" 
            name="status" 
            required>
            <option value="planned" {{ old('status', $isEdit ? $task->status : 'planned') === 'planned' ? 'selected' : '' }}>Planned</option>
            <option value="active" {{ old('status', $isEdit ? $task->status : '') === 'active' ? 'selected' : '' }}>Active</option>
            <option value="completed" {{ old('status', $isEdit ? $task->status : '') === 'completed' ? 'selected' : '' }}>Completed</option>
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="start_date" class="form-label">Start Date</label>
            <input 
                type="date" 
                class="form-control @error('start_date') is-invalid @enderror" 
                id="start_date" 
                name="start_date" 
                value="{{ old('start_date', $isEdit && $task->start_date ? $task->start_date->format('Y-m-d') : '') }}">
            @error('start_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label for="due_date" class="form-label">Due Date</label>
            <input 
                type="date" 
                class="form-control @error('due_date') is-invalid @enderror" 
                id="due_date" 
                name="due_date" 
                value="{{ old('due_date', $isEdit && $task->due_date ? $task->due_date->format('Y-m-d') : '') }}">
            @error('due_date')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Assign To</label>
        @include('partials.user-picker', [
            'users' => $users,
            'selectedUserIds' => old('assignees', $isEdit ? $task->users->pluck('id')->toArray() : []),
            'inputName' => 'assignees[]'
        ])
        @error('assignees')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    @if($isEdit)
        <div class="mb-3">
            <label for="column_id_select" class="form-label">Status Column</label>
            <select 
                class="form-select @error('column_id') is-invalid @enderror" 
                id="column_id_select" 
                name="column_id" 
                required>
                @foreach($columns as $col)
                    <option value="{{ $col->id }}" {{ $task->column_id == $col->id ? 'selected' : '' }}>
                        {{ $col->name }}
                    </option>
                @endforeach
            </select>
            @error('column_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    @endif

    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">{{ $isEdit ? 'Update Task' : 'Create Task' }}</button>
    </div>
</form>
