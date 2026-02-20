@php
    $favoriteProjectIds = $favoriteProjectIds ?? [];
    
    // Split into favorites and others - preserve controller's sort order unless alphabetical
    $currentSort = request('sort', 'alphabetical');
    
    if ($currentSort === 'alphabetical') {
        $favorites = $projects->filter(fn($p) => in_array($p->id, $favoriteProjectIds))->sortBy('name')->values();
        $others    = $projects->reject(fn($p) => in_array($p->id, $favoriteProjectIds))->sortBy('name')->values();
    } else {
        // Preserve controller's sort order
        $favorites = $projects->filter(fn($p) => in_array($p->id, $favoriteProjectIds))->values();
        $others    = $projects->reject(fn($p) => in_array($p->id, $favoriteProjectIds))->values();
    }
@endphp

@if($projects->isEmpty())
    @if(request()->hasAny(['search', 'scope', 'status', 'time', 'owner']))
        <div class="alert alert-info">
            <h5 class="alert-heading">No projects match your filters</h5>
            <p class="mb-0">Try adjusting your filters or <a href="{{ route('projects.index') }}" class="alert-link">view all projects</a>.</p>
        </div>
    @else
        @include('partials.empty-state', [
            'message' => 'No projects yet. Projects are top-level containers for organizing your work into phases and tasks.',
            'actionUrl' => route('projects.create'),
            'actionText' => 'Create your first project'
        ])
    @endif
@else
    {{-- Favorites section --}}
    @if($favorites->isNotEmpty())
        <div class="mb-4">
            <div class="d-flex align-items-center gap-2 mb-1 px-1">
                <span style="color: #f5a623; font-size: 0.85rem;">★</span>
                <small class="text-muted text-uppercase fw-semibold" style="letter-spacing: 0.07em; font-size: 0.7rem;">Favorites</small>
            </div>
            <div class="list-group border rounded" style="overflow: hidden;">
                @foreach($favorites as $project)
                    @include('projects.partials.project-list-row', ['project' => $project, 'isFavorited' => true])
                @endforeach
            </div>
        </div>
    @endif

    {{-- All other projects --}}
    @if($others->isNotEmpty())
        @if($favorites->isNotEmpty())
            <div class="d-flex align-items-center gap-2 mb-1 px-1">
                <small class="text-muted text-uppercase fw-semibold" style="letter-spacing: 0.07em; font-size: 0.7rem;">All Projects</small>
            </div>
        @endif
        <div class="list-group border rounded" style="overflow: hidden;">
            @foreach($others as $project)
                @include('projects.partials.project-list-row', ['project' => $project, 'isFavorited' => false])
            @endforeach
        </div>
    @endif
@endif
