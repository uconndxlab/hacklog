{{-- 
    Project Navigation Tabs
    Pure navigation - no action buttons
    @param \App\Models\Project $project
    @param string $currentView
--}}
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link @if($currentView === 'home') active @endif" href="{{ route('projects.show', $project) }}">
            Home
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($currentView === 'board') active @endif" href="{{ route('projects.board', $project) }}">
            Board
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($currentView === 'schedule') active @endif" href="{{ route('projects.schedule', $project) }}">
            Schedule
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($currentView === 'timeline') active @endif" href="{{ route('projects.timeline', $project) }}">
            Timeline
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($currentView === 'resources') active @endif" href="{{ route('projects.resources.index', $project) }}">
            Resources
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($currentView === 'sharing') active @endif" href="{{ route('projects.sharing', $project) }}">
            Sharing
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link @if($currentView === 'settings') active @endif" href="{{ route('projects.edit', $project) }}">
            Settings
        </a>
    </li>
</ul>
