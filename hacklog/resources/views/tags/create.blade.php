@extends('layouts.app')

@section('title', 'Create Tag')

@section('content')
<div class="row">
    <div class="col-lg-6">
        <h1 class="mb-4">Create Tag</h1>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('tags.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Tag Name</label>
                        <input
                            type="text"
                            class="form-control @error('name') is-invalid @enderror"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="e.g. Security"
                            required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="color" class="form-label">Color (optional)</label>
                        <input
                            type="text"
                            class="form-control @error('color') is-invalid @enderror"
                            id="color"
                            name="color"
                            value="{{ old('color') }}"
                            placeholder="#3366CC">
                        <div class="form-text">Use hex format like #3366CC.</div>
                        @error('color')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Create Tag</button>
                        <a href="{{ route('tags.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
