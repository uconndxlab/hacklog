{{-- Project Navigation Tabs --}}
<div class="mb-4 project-nav-wrapper">
    <ul class="nav nav-tabs flex-nowrap">
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
</div>
