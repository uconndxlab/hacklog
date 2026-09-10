{{-- 
    Project Header Component
    Displays project identity consistently across all project pages
    @param \App\Models\Project $project
--}}
<div class="mb-3">
    <h1 class="mb-2">{{ $project->name }}</h1>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="badge" style="background-color: {{ $project->statusColor() }}; color: {{ $project->statusTextColor() }}">
            {{ $project->statusLabel() }}
        </span>
        <span class="text-muted small">
            Created {{ $project->created_at->format('M j, Y') }}
        </span>

        @foreach($project->tags as $tag)
            <span class="badge rounded-pill text-bg-light border">
                {{ $tag->name }}
            </span>
        @endforeach

        @if($project->projectTypeLabel())
            <span class="badge rounded-pill text-bg-secondary">{{ $project->projectTypeLabel() }}</span>
        @endif
    </div>
</div>
