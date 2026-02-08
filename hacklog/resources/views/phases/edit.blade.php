@extends('layouts.app')

@section('title', 'Edit Phase - ' . $phase->name)

@section('content')
@include('projects.partials.project-header')
@include('projects.partials.project-nav', ['currentView' => 'settings'])

<div class="row">
    <div class="col-lg-8">
        <div class="mb-4">
            <h2 class="h4">Edit Phase: {{ $phase->name }}</h2>
        </div>

        <form action="{{ route('projects.phases.update', [$project, $phase]) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label for="name" class="form-label">Phase Name</label>
                <input 
                    type="text" 
                    class="form-control @error('name') is-invalid @enderror" 
                    id="name" 
                    name="name" 
                    value="{{ old('name', $phase->name) }}" 
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
                    value="{{ old('description', $phase->description) }}">
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
                    <option value="planned" {{ old('status', $phase->status) === 'planned' ? 'selected' : '' }}>Planned</option>
                    <option value="active" {{ old('status', $phase->status) === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="completed" {{ old('status', $phase->status) === 'completed' ? 'selected' : '' }}>Completed</option>
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
                        value="{{ old('start_date', $phase->start_date?->format('Y-m-d')) }}">
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
                        value="{{ old('end_date', $phase->end_date?->format('Y-m-d')) }}">
                    @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Update Phase</button>
                <a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <hr class="my-4">

        <form action="{{ route('projects.phases.destroy', [$project, $phase]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this phase?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm">Delete Phase</button>
        </form>
    </div>
</div>
@endsection
