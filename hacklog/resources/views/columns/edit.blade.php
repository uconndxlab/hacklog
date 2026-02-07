@extends('layouts.app')

@section('title', 'Edit Column - ' . $column->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
        <li class="breadcrumb-item"><a href="{{ route('projects.columns.index', $project) }}">Kanban Columns</a></li>
        <li class="breadcrumb-item active" aria-current="page">Edit {{ $column->name }}</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-6">
        <h1 class="mb-4">Edit Column</h1>

        <form action="{{ route('projects.columns.update', [$project, $column]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="position" class="form-label">Position</label>
                <input 
                    type="number" 
                    class="form-control @error('position') is-invalid @enderror" 
                    id="position" 
                    name="position" 
                    value="{{ old('position', $column->position) }}"
                    required
                    min="0">
                <div class="form-text">Lower numbers appear first in the workflow</div>
                @error('position')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="name" class="form-label">Column Name</label>
                <input 
                    type="text" 
                    class="form-control @error('name') is-invalid @enderror" 
                    id="name" 
                    name="name" 
                    value="{{ old('name', $column->name) }}" 
                    required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input 
                        type="checkbox" 
                        class="form-check-input" 
                        id="is_default" 
                        name="is_default"
                        value="1"
                        {{ old('is_default', $column->is_default) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_default">
                        Set as default column
                    </label>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Column</button>
                <a href="{{ route('projects.columns.index', $project) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <hr class="my-4">

        <form action="{{ route('projects.columns.destroy', [$project, $column]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this column?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete Column</button>
        </form>
    </div>
</div>
@endsection
