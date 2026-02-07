@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0">Projects</h1>
    <a href="{{ route('projects.create') }}" class="btn btn-primary">New Project</a>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            {{-- User Assignment Filter --}}
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    @php
                        $assigned = request('assigned');
                        $assignedLabel = 'All Projects';
                        if ($assigned === 'me') {
                            $assignedLabel = 'My Projects';
                        } elseif ($assigned && is_numeric($assigned)) {
                            $assignedUser = \App\Models\User::find($assigned);
                            $assignedLabel = $assignedUser ? $assignedUser->name : 'All Projects';
                        }
                    @endphp
                    {{ $assignedLabel }}
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a class="dropdown-item {{ !request('assigned') ? 'active' : '' }}" 
                           href="{{ request()->fullUrlWithQuery(['assigned' => null]) }}"
                           hx-get="{{ request()->fullUrlWithQuery(['assigned' => null]) }}"
                           hx-target="#projects-list"
                           hx-push-url="true">
                            All Projects
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ request('assigned') === 'me' ? 'active' : '' }}" 
                           href="{{ request()->fullUrlWithQuery(['assigned' => 'me']) }}"
                           hx-get="{{ request()->fullUrlWithQuery(['assigned' => 'me']) }}"
                           hx-target="#projects-list"
                           hx-push-url="true">
                            My Projects
                        </a>
                    </li>
                    @if(Auth::user()->isAdmin())
                        <li><hr class="dropdown-divider"></li>
                        @php
                            $users = \App\Models\User::where('active', true)->orderBy('name')->get();
                        @endphp
                        @foreach($users as $user)
                            <li>
                                <a class="dropdown-item {{ request('assigned') == $user->id ? 'active' : '' }}" 
                                   href="{{ request()->fullUrlWithQuery(['assigned' => $user->id]) }}"
                                   hx-get="{{ request()->fullUrlWithQuery(['assigned' => $user->id]) }}"
                                   hx-target="#projects-list"
                                   hx-push-url="true">
                                    {{ $user->name }}
                                </a>
                            </li>
                        @endforeach
                    @endif
                </ul>
            </div>

            {{-- Status Filter --}}
            <div class="d-flex align-items-center gap-2">
                <label for="status" class="form-label mb-0 small text-muted">Status</label>
                <select class="form-select form-select-sm" id="status" name="status" 
                        onchange="window.location.href = this.value">
                    <option value="{{ request()->fullUrlWithQuery(['status' => null]) }}" 
                            {{ !request('status') ? 'selected' : '' }}>
                        All Statuses
                    </option>
                    <option value="{{ request()->fullUrlWithQuery(['status' => 'planned']) }}" 
                            {{ request('status') === 'planned' ? 'selected' : '' }}>
                        Planned
                    </option>
                    <option value="{{ request()->fullUrlWithQuery(['status' => 'active']) }}" 
                            {{ request('status') === 'active' ? 'selected' : '' }}>
                        Active
                    </option>
                    <option value="{{ request()->fullUrlWithQuery(['status' => 'paused']) }}" 
                            {{ request('status') === 'paused' ? 'selected' : '' }}>
                        Paused
                    </option>
                    <option value="{{ request()->fullUrlWithQuery(['status' => 'completed']) }}" 
                            {{ request('status') === 'completed' ? 'selected' : '' }}>
                        Completed
                    </option>
                    <option value="{{ request()->fullUrlWithQuery(['status' => 'archived']) }}" 
                            {{ request('status') === 'archived' ? 'selected' : '' }}>
                        Archived
                    </option>
                </select>
            </div>

            {{-- Upcoming Work Filter --}}
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="upcomingFilter" 
                       {{ request('upcoming') ? 'checked' : '' }}
                       onchange="window.location.href = this.checked ? '{{ request()->fullUrlWithQuery(['upcoming' => '1']) }}' : '{{ request()->fullUrlWithQuery(['upcoming' => null]) }}'">
                <label class="form-check-label" for="upcomingFilter">
                    Has Upcoming Work
                </label>
            </div>

            {{-- Clear Filters --}}
            @if(request()->hasAny(['assigned', 'status', 'upcoming']))
                <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary btn-sm ms-auto">Clear All Filters</a>
            @endif
        </div>
    </div>
</div>

{{-- Projects List --}}
<div id="projects-list">
    @include('projects.partials.projects-list', ['projects' => $projects])
</div>
@endsection
