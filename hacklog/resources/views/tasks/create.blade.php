@extends('layouts.app')

@section('title', 'Create Task')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Create Task</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="mb-4">
            <h1>Create Task</h1>
        </div>

        @if($columns->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No kanban columns defined for this project. Set up your workflow by creating columns first.',
                'actionUrl' => route('projects.columns.index', $project),
                'actionText' => 'Manage columns'
            ])
        @else
            <form action="{{ route('projects.tasks.store', $project) }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="phase_id" class="form-label">Phase</label>
                    <select
                        class="form-select @error('phase_id') is-invalid @enderror"
                        id="phase_id"
                        name="phase_id"
                        required>
                        <option value="">Choose a phase...</option>
                        @php
                            $defaultPhaseId = old('phase_id', $phase->id);
                        @endphp
                        @foreach($phases as $phaseOption)
                            <option value="{{ $phaseOption->id }}"
                                {{ $defaultPhaseId == $phaseOption->id ? 'selected' : '' }}>
                                {{ $phaseOption->name }}
                                @if($phaseOption->status === 'active')
                                    (Active)
                                @elseif($phaseOption->status === 'planned')
                                    (Planned)
                                @elseif($phaseOption->status === 'completed')
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
                        value="{{ old('title') }}" 
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
                        value="{{ old('description') }}">
                    <trix-editor input="description" class="@error('description') is-invalid @enderror"></trix-editor>
                    @error('description')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="column_id" class="form-label">Status Column</label>
                        <select 
                            class="form-select @error('column_id') is-invalid @enderror" 
                            id="column_id" 
                            name="column_id" 
                            required>
                            <option value="">Choose a status...</option>
                            @foreach($columns as $column)
                                <option value="{{ $column->id }}" 
                                    {{ old('column_id', $column->is_default ? $column->id : '') == $column->id ? 'selected' : '' }}>
                                    {{ $column->name }}
                                </option>
                            @endforeach
                        </select>
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
                            <option value="planned" {{ old('status', 'planned') === 'planned' ? 'selected' : '' }}>Planned</option>
                            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="awaiting_feedback" {{ old('status') === 'awaiting_feedback' ? 'selected' : '' }}>Awaiting Feedback</option>
                            <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed</option>
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
                            value="{{ old('start_date') }}">
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
                            value="{{ old('due_date') }}">
                        @error('due_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Assign To</label>
                    @include('partials.user-picker', [
                        'users' => $users,
                        'selectedUserIds' => old('assignees', []),
                        'inputName' => 'assignees[]'
                    ])
                    @error('assignees')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Task</button>
                    <a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
