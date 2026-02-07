@extends('layouts.app')

@section('title', 'Edit Epic - ' . $epic->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.epics.index', $project) }}">Epics</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}">{{ $epic->name }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="mb-4">
            <h1 class="mb-1">Edit Epic</h1>
            <p class="text-muted mb-0">{{ $project->name }}</p>
        </div>

        <form action="{{ route('projects.epics.update', [$project, $epic]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name" class="form-label">Epic Name</label>
                <input 
                    type="text" 
                    class="form-control @error('name') is-invalid @enderror" 
                    id="name" 
                    name="name" 
                    value="{{ old('name', $epic->name) }}" 
                    required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <input 
                    id="description" 
                    type="hidden" 
                    name="description" 
                    value="{{ old('description', $epic->description) }}">
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
                    <option value="planned" {{ old('status', $epic->status) === 'planned' ? 'selected' : '' }}>Planned</option>
                    <option value="active" {{ old('status', $epic->status) === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="completed" {{ old('status', $epic->status) === 'completed' ? 'selected' : '' }}>Completed</option>
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
                        value="{{ old('start_date', $epic->start_date?->format('Y-m-d')) }}">
                    @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input 
                        type="date" 
                        class="form-control @error('end_date') is-invalid @enderror" 
                        id="end_date" 
                        name="end_date" 
                        value="{{ old('end_date', $epic->end_date?->format('Y-m-d')) }}">
                    @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Epic</button>
                <a href="{{ route('projects.board', ['project' => $project, 'epic' => $epic->id]) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <hr class="my-4">

        <form action="{{ route('projects.epics.destroy', [$project, $epic]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this epic?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete Epic</button>
        </form>
    </div>
</div>
@endsection
