{{-- 
    Project Header Component
    Displays project identity consistently across all project pages
    @param \App\Models\Project $project
--}}
<div class="mb-3">
    <h1 class="mb-2">{{ $project->name }}</h1>
    <div class="d-flex align-items-center gap-2">
        <span class="badge 
            @if($project->status === 'active') bg-success
            @elseif($project->status === 'paused') bg-warning text-dark
            @else bg-secondary
            @endif">
            {{ ucfirst($project->status) }}
        </span>
        <span class="text-muted small">
            Created {{ $project->created_at->format('M j, Y') }}
        </span>
    </div>
</div>
