{{-- Compact project list row with star toggle --}}
<div class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2 px-3" style="border-left: none; border-right: none;">
    {{-- Star toggle --}}
    <form method="POST" action="{{ route('projects.favorites.toggle', $project) }}" class="d-inline m-0 p-0 lh-1">
        @csrf
        <button
            type="button"
            class="btn btn-link p-0 border-0 lh-1"
            style="font-size: 1.15rem; width: 1.4rem; color: {{ $isFavorited ? '#f5a623' : '#ccc' }}; text-decoration: none;"
            hx-post="{{ route('projects.favorites.toggle', $project) }}"
            hx-target="#projects-list"
            hx-swap="innerHTML"
            hx-include="[name='search'], [name='sort'], [name='scope'], [name='status'], [name='time'], [name='owner']"
            title="{{ $isFavorited ? 'Remove from favorites' : 'Add to favorites' }}"
            onmouseover="this.style.color='#f5a623'"
            onmouseout="this.style.color='{{ $isFavorited ? '#f5a623' : '#ccc' }}'">
            ★
        </button>
    </form>

    {{-- Name --}}
    <a href="{{ route('projects.board', $project) }}"
       class="flex-grow-1 fw-medium text-decoration-none text-reset"
       style="min-width: 0;">
        {{ $project->name }}
    </a>

    {{-- Status badge --}}
    <span class="badge rounded-pill border text-muted bg-transparent fw-normal d-none d-sm-inline"
          style="font-size: 0.7rem;">
        {{ ucfirst($project->status) }}
    </span>

    {{-- Arrow --}}
    <span class="text-muted" style="font-size: 0.8rem; opacity: 0.4;">›</span>
</div>
