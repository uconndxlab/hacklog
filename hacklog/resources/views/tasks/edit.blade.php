@extends('layouts.app')

@section('title', 'Edit Task - ' . $task->title)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.epics.index', $project) }}">Epics</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}">{{ $epic->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.epics.tasks.show', [$project, $epic, $task]) }}">{{ $task->title }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="mb-4">
            <h1 class="mb-1">Edit Task</h1>
            <p class="text-muted mb-0">{{ $epic->name }}</p>
        </div>

        <form 
            action="{{ route('projects.epics.tasks.update', [$project, $epic, $task]) }}" 
            method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="title" class="form-label">Task Title</label>
                <input 
                    type="text" 
                    class="form-control @error('title') is-invalid @enderror" 
                    id="title" 
                    name="title" 
                    value="{{ old('title', $task->title) }}" 
                    required>
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
                    value="{{ old('description', $task->description) }}">
                <trix-editor input="description" class="@error('description') is-invalid @enderror"></trix-editor>
                @error('description')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="column_id" class="form-label">Column</label>
                    <select 
                        class="form-select @error('column_id') is-invalid @enderror" 
                        id="column_id" 
                        name="column_id" 
                        required>
                        @foreach($columns as $column)
                            <option value="{{ $column->id }}" 
                                {{ old('column_id', $task->column_id) == $column->id ? 'selected' : '' }}>
                                {{ $column->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Changing the column will move the task</div>
                    @error('column_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status</label>
                    <select 
                        class="form-select @error('status') is-invalid @enderror" 
                        id="status" 
                        name="status" 
                        required>
                        <option value="planned" {{ old('status', $task->status) === 'planned' ? 'selected' : '' }}>Planned</option>
                        <option value="active" {{ old('status', $task->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="completed" {{ old('status', $task->status) === 'completed' ? 'selected' : '' }}>Completed</option>
                    </select>
                    @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input 
                        type="date" 
                        class="form-control @error('start_date') is-invalid @enderror" 
                        id="start_date" 
                        name="start_date" 
                        value="{{ old('start_date', $task->start_date?->format('Y-m-d')) }}">
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
                        value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}">
                    @error('due_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Assign To</label>
                @include('partials.user-picker', [
                    'users' => $users,
                    'selectedUserIds' => old('assignees', $task->users->pluck('id')->toArray()),
                    'inputName' => 'assignees[]'
                ])
                @error('assignees')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Task</button>
                <a href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <hr class="my-4">

        <form action="{{ route('projects.epics.tasks.destroy', [$project, $epic, $task]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this task?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete Task</button>
        </form>
    </div>
</div>
@endsection
