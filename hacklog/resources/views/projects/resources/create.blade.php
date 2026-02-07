@extends('layouts.app')

@section('title', 'Add Resource - ' . $project->name)

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="mb-4">
            <h1>Add Resource</h1>
            <p class="text-muted">{{ $project->name }}</p>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('projects.resources.store', $project) }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="title" class="form-label">Title</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">Select type...</option>
                            <option value="link" {{ old('type') === 'link' ? 'selected' : '' }}>Link</option>
                            <option value="note" {{ old('type') === 'note' ? 'selected' : '' }}>Note</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="url-field" style="display: none;">
                        <label for="url" class="form-label">URL</label>
                        <input type="url" class="form-control @error('url') is-invalid @enderror" id="url" name="url" value="{{ old('url') }}">
                        <div class="form-text">Enter the full URL including http:// or https://</div>
                        @error('url')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3" id="content-field" style="display: none;">
                        <label for="content" class="form-label">Content</label>
                        <input id="content" type="hidden" name="content" value="{{ old('content') }}">
                        <trix-editor input="content" class="@error('content') is-invalid @enderror"></trix-editor>
                        @error('content')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Add Resource</button>
                        <a href="{{ route('projects.resources.index', $project) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Toggle fields based on resource type
    const typeSelect = document.getElementById('type');
    const urlField = document.getElementById('url-field');
    const contentField = document.getElementById('content-field');
    const urlInput = document.getElementById('url');
    const contentInput = document.getElementById('content');

    function toggleFields() {
        const selectedType = typeSelect.value;
        
        if (selectedType === 'link') {
            urlField.style.display = 'block';
            contentField.style.display = 'none';
            urlInput.required = true;
            contentInput.required = false;
        } else if (selectedType === 'note') {
            urlField.style.display = 'none';
            contentField.style.display = 'block';
            urlInput.required = false;
            contentInput.required = true;
        } else {
            urlField.style.display = 'none';
            contentField.style.display = 'none';
            urlInput.required = false;
            contentInput.required = false;
        }
    }

    typeSelect.addEventListener('change', toggleFields);
    
    // Initialize on page load
    toggleFields();
</script>
@endpush
@endsection
