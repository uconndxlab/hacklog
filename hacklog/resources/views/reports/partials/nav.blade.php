<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="mb-0">{{ $title }}</h1>
        @if(!empty($subtitle))
            <small class="text-muted">{{ $subtitle }}</small>
        @endif
    </div>
    <ul class="nav nav-pills">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}" href="{{ route('reports.index') }}">Inventory</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('reports.editor') ? 'active' : '' }}" href="{{ route('reports.editor') }}">Editor</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('reports.workload') ? 'active' : '' }}" href="{{ route('reports.workload') }}">Workload</a>
        </li>
    </ul>
</div>
