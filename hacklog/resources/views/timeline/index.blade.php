@extends('layouts.app')

@section('title', 'Organization Timeline')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1>Organization Timeline</h1>
                <p class="text-muted mb-0">Phase schedules across all projects</p>
            </div>
            <div class="dropdown">
                <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    Add Phase
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="max-height: 60vh; overflow-y: auto;">
                    @foreach($filterOptions['projects'] as $projectOption)
                        <li>
                            <button class="dropdown-item" type="button"
                                    hx-get="{{ route('projects.phases.create', $projectOption) }}"
                                    hx-target="#phaseCreateModalContent"
                                    hx-swap="innerHTML"
                                    onclick="bootstrap.Modal.getOrCreateInstance(document.getElementById('phaseCreateModal')).show()">
                                {{ $projectOption->name }}
                            </button>
                        </li>
                    @endforeach
                    @if($filterOptions['projects']->isEmpty())
                        <li><span class="dropdown-item-text text-muted">No projects available</span></li>
                    @endif
                </ul>
            </div>
        </div>

        @include('timeline.partials.page')
    </div>
</div>
@endsection
