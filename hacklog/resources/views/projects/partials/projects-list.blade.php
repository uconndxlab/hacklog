@if($projects->isEmpty())
    @if(request()->hasAny(['assigned', 'status', 'upcoming']))
        <div class="alert alert-info">
            <h5 class="alert-heading">No projects match your filters</h5>
            <p class="mb-0">Try adjusting your filters or <a href="{{ route('projects.index') }}" class="alert-link">view all projects</a>.</p>
        </div>
    @else
        @include('partials.empty-state', [
            'message' => 'No projects yet. Projects are top-level containers for organizing your work into epics and tasks.',
            'actionUrl' => route('projects.create'),
            'actionText' => 'Create your first project'
        ])
    @endif
@else
    <div class="row">
        @foreach($projects as $project)
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h2 class="h5 card-title mb-2">{{ $project->name }}</h2>
                        <div class="mb-2">
                            <span class="badge 
                                @if($project->status === 'active') bg-success
                                @elseif($project->status === 'paused') bg-warning text-dark
                                @else bg-secondary
                                @endif">
                                {{ ucfirst($project->status) }}
                            </span>
                        </div>
                        @if($project->description)
                            <p class="card-text text-muted small">
                                {{ Str::limit(strip_tags($project->description), 100) }}
                            </p>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href="{{ route('projects.show', $project) }}" class="btn btn-sm btn-outline-primary">View</a>
                        <a href="{{ route('projects.edit', $project) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
