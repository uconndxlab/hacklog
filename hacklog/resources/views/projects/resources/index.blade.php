@extends('layouts.app')

@section('title', $project->name . ' - Resources')

@section('content')
<div class="row">
    <div class="col-lg-12">
        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'resources'])

        {{-- Page Actions --}}
        <div class="d-flex justify-content-end mb-4">
            <a href="{{ route('projects.resources.create', $project) }}" class="btn btn-primary">Add Resource</a>
        </div>

        @if($resources->isEmpty())
            @include('partials.empty-state', [
                'message' => 'No resources yet. Add curated links and notes to create a knowledge hub for this project.',
                'actionUrl' => route('projects.resources.create', $project),
                'actionText' => 'Add your first resource'
            ])
        @else
            <div class="card">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($resources as $resource)
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <h2 class="h5 mb-0 me-2">{{ $resource->title }}</h2>
                                            <span class="badge bg-secondary">{{ ucfirst($resource->type) }}</span>
                                        </div>
                                        
                                        @if($resource->type === 'link')
                                            <div class="mb-2">
                                                <a href="{{ $resource->url }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                                    {{ $resource->url }}
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" class="bi bi-box-arrow-up-right" viewBox="0 0 16 16">
                                                        <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/>
                                                        <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/>
                                                    </svg>
                                                </a>
                                            </div>
                                        @else
                                            <div class="text-muted small">
                                                {{ $resource->getExcerpt(150) }}
                                            </div>
                                        @endif
                                    </div>
                                    
                                    <div class="d-flex flex-column gap-1 ms-3">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="{{ route('projects.resources.edit', [$project, $resource]) }}" class="btn btn-outline-secondary">Edit</a>
                                            <form action="{{ route('projects.resources.destroy', [$project, $resource]) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this resource?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger">Delete</button>
                                            </form>
                                        </div>
                                        <div class="btn-group btn-group-sm" role="group">
                                            @if($resource->canMoveUp())
                                                <form action="{{ route('projects.resources.move-up', [$project, $resource]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-secondary">↑</button>
                                                </form>
                                            @else
                                                <button type="button" class="btn btn-outline-secondary" disabled>↑</button>
                                            @endif
                                            
                                            @if($resource->canMoveDown())
                                                <form action="{{ route('projects.resources.move-down', [$project, $resource]) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-secondary">↓</button>
                                                </form>
                                            @else
                                                <button type="button" class="btn btn-outline-secondary" disabled>↓</button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                
                                @if($resource->type === 'note' && $resource->content)
                                    <details class="mt-3">
                                        <summary class="text-primary" style="cursor: pointer;">View full content</summary>
                                        <div class="mt-2 p-3 bg-light rounded trix-content">
                                            {!! $resource->content !!}
                                        </div>
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        <div class="mt-3">
            <a href="{{ route('projects.show', $project) }}" class="btn btn-outline-secondary">Back to Project</a>
        </div>
    </div>
</div>

@push('scripts')
<style>
    .trix-content {
        line-height: 1.6;
    }
    .trix-content h1 {
        font-size: 1.25rem;
        margin-top: 0.75rem;
        margin-bottom: 0.5rem;
    }
    .trix-content p {
        margin-bottom: 0.5rem;
    }
    .trix-content ul, .trix-content ol {
        margin-bottom: 0.5rem;
        padding-left: 1.5rem;
    }
    .trix-content blockquote {
        border-left: 3px solid #dee2e6;
        padding-left: 0.75rem;
        margin-left: 0;
        color: #6c757d;
    }
</style>
@endpush
@endsection
