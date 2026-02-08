@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Projects</h1>
        @if(Auth::user()->isClient())
            <small class="text-muted">Showing projects shared with you</small>
        @endif
    </div>
    <div class="d-flex align-items-center gap-2">
        @if(Auth::user()->isClient())
            <span class="badge bg-info">Client Access</span>
        @endif
        <a href="{{ route('projects.create') }}" class="btn btn-primary">New Project</a>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-4">
    <div class="card-body">
        {{-- Search --}}
        <div class="mb-3">
            <input type="text" 
                   class="form-control" 
                   id="search" 
                   name="search" 
                   placeholder="Search projects by name or description..."
                   value="{{ request('search') }}"
                   hx-get="{{ route('projects.index') }}"
                   hx-trigger="input changed delay:500ms, search"
                   hx-target="#projects-list"
                   hx-include="[name='scope'], [name='status'], [name='time'], [name='owner']"
                   hx-push-url="true">
        </div>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            {{-- Scope Filter --}}
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small text-muted">Scope</label>
                <select class="form-select form-select-sm" name="scope" style="width: auto;"
                        hx-get="{{ route('projects.index') }}"
                        hx-trigger="change"
                        hx-target="#projects-list"
                        hx-include="[name='search'], [name='status'], [name='time'], [name='owner']"
                        hx-push-url="true">
                    <option value="all" {{ request('scope', Auth::user()->isAdmin() ? 'all' : 'assigned') === 'all' ? 'selected' : '' }}>
                        All projects
                    </option>
                    <option value="assigned" {{ request('scope', Auth::user()->isAdmin() ? 'all' : 'assigned') === 'assigned' ? 'selected' : '' }}>
                        Assigned to me
                    </option>
                    <option value="member" {{ request('scope') === 'member' ? 'selected' : '' }}>
                        Projects I'm on
                    </option>
                    <option value="contributor" {{ request('scope') === 'contributor' ? 'selected' : '' }}>
                        I'm a contributor
                    </option>
                </select>
            </div>

            {{-- Status Filter --}}
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small text-muted">Status</label>
                <select class="form-select form-select-sm" name="status" style="width: auto;"
                        hx-get="{{ route('projects.index') }}"
                        hx-trigger="change"
                        hx-target="#projects-list"
                        hx-include="[name='search'], [name='scope'], [name='time'], [name='owner']"
                        hx-push-url="true">
                    <option value="active" {{ request('status', 'active') === 'active' ? 'selected' : '' }}>
                        Active
                    </option>
                    <option value="planned" {{ request('status') === 'planned' ? 'selected' : '' }}>
                        Planned
                    </option>
                    <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>
                        On hold
                    </option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>
                        Completed
                    </option>
                    <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>
                        Archived
                    </option>
                </select>
            </div>

            {{-- Time-based Filter --}}
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small text-muted">Timeline</label>
                <select class="form-select form-select-sm" name="time" style="width: auto;"
                        hx-get="{{ route('projects.index') }}"
                        hx-trigger="change"
                        hx-target="#projects-list"
                        hx-include="[name='search'], [name='scope'], [name='status'], [name='owner']"
                        hx-push-url="true">
                    <option value="" {{ !request('time') ? 'selected' : '' }}>
                        All timeframes
                    </option>
                    <option value="overdue" {{ request('time') === 'overdue' ? 'selected' : '' }}>
                        Overdue
                    </option>
                    <option value="7" {{ request('time') === '7' ? 'selected' : '' }}>
                        Due in 7 days
                    </option>
                    <option value="14" {{ request('time') === '14' ? 'selected' : '' }}>
                        Due in 14 days
                    </option>
                    <option value="30" {{ request('time') === '30' ? 'selected' : '' }}>
                        Due in 30 days
                    </option>
                </select>
            </div>

            {{-- Admin: Owner Filter --}}
            @if(Auth::user()->isAdmin())
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 small text-muted">Owner</label>
                    <select class="form-select form-select-sm" name="owner" style="width: auto;"
                            hx-get="{{ route('projects.index') }}"
                            hx-trigger="change"
                            hx-target="#projects-list"
                            hx-include="[name='search'], [name='scope'], [name='status'], [name='time']"
                            hx-push-url="true">
                        <option value="" {{ !request('owner') ? 'selected' : '' }}>
                            Any owner
                        </option>
                        @php
                            $users = \App\Models\User::where('active', true)->orderBy('name')->get();
                        @endphp
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ request('owner') == $user->id ? 'selected' : '' }}>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            {{-- Clear Filters --}}
            @if(request()->hasAny(['search', 'scope', 'time', 'owner']) || request('status', 'active') !== 'active')
                <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary btn-sm ms-auto">Clear Filters</a>
            @endif
        </div>
    </div>
</div>

{{-- Projects List --}}
<div id="projects-list">
    @include('projects.partials.projects-list', ['projects' => $projects])
</div>
@endsection
