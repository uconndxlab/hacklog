@extends('layouts.app')

@section('title', $project->name . ' - Team & Sharing')

@section('content')
@include('projects.partials.project-header')
@include('projects.partials.project-nav', ['currentView' => 'sharing'])

<div class="row">
    <div class="col-12 mb-3">
        <p class="lead text-muted mb-0">Assign team members responsible for this project and add external clients for collaboration</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    $teamShares   = $shares->filter(fn($s) => $s->isUserShare() && $s->user && !$s->user->isClient());
    $clientShares = $shares->filter(fn($s) => $s->isUserShare() && $s->user && $s->user->isClient());
    $roleShares   = $shares->filter(fn($s) => $s->isRoleShare());

    $availableTeam    = $availableUsers->filter(fn($u) => !$u->isClient())->values();
    $availableClients = $availableUsers->filter(fn($u) => $u->isClient())->values();
@endphp

<div class="row">
    <div class="col-lg-8">

        {{-- Explanation --}}
        <div class="card mb-4 bg-light">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6 class="fw-semibold mb-1">
                            Project Team
                            <span class="badge bg-secondary fw-normal ms-1" style="font-size:0.7rem;">Admin / Team</span>
                        </h6>
                        <p class="small text-muted mb-0">
                            Team members and admins you assign here are explicitly responsible for the outcomes of this project.
                            Admins and team members already have system-wide access — adding them here designates ownership and surfaces them in the portfolio table view.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-semibold mb-1">
                            External Clients
                            <span class="badge bg-info text-dark fw-normal ms-1" style="font-size:0.7rem;">Client</span>
                        </h6>
                        <p class="small text-muted mb-0">
                            Clients are external collaborators added for visibility into project progress.
                            They only see projects explicitly shared with them, and cannot access sharing settings, manage assignments, or view start dates.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Project Team --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">Project Team</h2>
                @if($teamShares->isNotEmpty())
                    <span class="badge bg-secondary">{{ $teamShares->count() }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($teamShares->isEmpty())
                    <p class="text-muted small mb-0">
                        No team members explicitly assigned yet.
                        Admins and team members already have access — add them here to designate explicit responsibility for this project.
                    </p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($teamShares as $share)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-semibold">{{ $share->user->name }}</span>
                                        @if($share->user->isAdmin())
                                            <span class="badge bg-danger ms-1" style="font-size:0.65rem;">Admin</span>
                                        @else
                                            <span class="badge bg-secondary ms-1" style="font-size:0.65rem;">Team</span>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('projects.shares.destroy', [$project, $share->id]) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Remove {{ addslashes($share->user->name) }} from the project team?')">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- External Clients --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">External Clients</h2>
                @if($clientShares->isNotEmpty())
                    <span class="badge bg-info text-dark">{{ $clientShares->count() }}</span>
                @endif
            </div>
            <div class="card-body">
                @if($clientShares->isEmpty())
                    <p class="text-muted small mb-0">No clients added yet.</p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($clientShares as $share)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-semibold">{{ $share->user->name }}</span>
                                        <span class="badge bg-info text-dark ms-1" style="font-size:0.65rem;">Client</span>
                                        <div class="text-muted small">{{ $share->user->email }}</div>
                                    </div>
                                    <form method="POST" action="{{ route('projects.shares.destroy', [$project, $share->id]) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Remove {{ addslashes($share->user->name) }} from this project?')">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Role-based shares (only shown when any exist) --}}
        @if($roleShares->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h5 mb-0">Role-based Access</h2>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @foreach($roleShares as $share)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <span class="fw-semibold">All {{ ucfirst($share->getRoleName()) }} users</span>
                                        <small class="text-muted d-block">Shared with entire role</small>
                                    </div>
                                    <form method="POST" action="{{ route('projects.shares.destroy', [$project, $share->id]) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Remove role-based access for all {{ $share->getRoleName() }} users?')">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

    </div>

    <div class="col-lg-4">

        {{-- Add Team Member --}}
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="h5 mb-0">Add Team Member</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Assign an admin or team member as explicitly responsible for this project's outcomes.</p>
                @if($availableTeam->isEmpty())
                    <p class="text-muted small mb-0">All team members and admins have already been added.</p>
                @else
                    <form method="POST" action="{{ route('projects.shares.store', $project) }}">
                        @csrf
                        <input type="hidden" name="shareable_type" value="user">
                        <div class="mb-3">
                            <label for="team_member_id" class="form-label">Select person</label>
                            <select name="shareable_id" id="team_member_id"
                                    class="form-select @error('shareable_id') is-invalid @enderror" required>
                                <option value="">Choose a team member…</option>
                                @php
                                    $optAdmins = $availableTeam->filter(fn($u) => $u->isAdmin());
                                    $optTeam   = $availableTeam->filter(fn($u) => !$u->isAdmin());
                                @endphp
                                @if($optAdmins->isNotEmpty())
                                    <optgroup label="Admins">
                                        @foreach($optAdmins as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                @if($optTeam->isNotEmpty())
                                    <optgroup label="Team Members">
                                        @foreach($optTeam as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                            @error('shareable_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Add to Team</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Add Client --}}
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="h5 mb-0">Add External Client</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">Grant a client visibility into this project for collaboration.</p>
                @if($availableClients->isEmpty())
                    <p class="text-muted small mb-0">All clients have already been added, or no client accounts exist.</p>
                @else
                    <form method="POST" action="{{ route('projects.shares.store', $project) }}">
                        @csrf
                        <input type="hidden" name="shareable_type" value="user">
                        <div class="mb-3">
                            <label for="client_id" class="form-label">Select client</label>
                            <select name="shareable_id" id="client_id" class="form-select" required>
                                <option value="">Choose a client…</option>
                                @foreach($availableClients as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-primary w-100">Add Client</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Share with Role --}}
        <div class="card">
            <div class="card-header">
                <h3 class="h5 mb-0">Share with a Role</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Share with all current and future users of a given role.
                </p>
                @php
                    $availableRoles = [];
                    if (!in_array('client', $sharedRoles)) {
                        $availableRoles[] = ['value' => 'client', 'label' => 'All Clients'];
                    }
                    if (!in_array('team', $sharedRoles)) {
                        $availableRoles[] = ['value' => 'team', 'label' => 'All Team Members'];
                    }
                @endphp
                @if(empty($availableRoles))
                    <p class="text-muted small mb-0">All roles have been shared with.</p>
                @else
                    <form method="POST" action="{{ route('projects.shares.store', $project) }}">
                        @csrf
                        <input type="hidden" name="shareable_type" value="role">
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Select role</label>
                            <select name="shareable_id" id="role_id" class="form-select" required>
                                <option value="">Choose a role…</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role['value'] }}">{{ $role['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary w-100">Share with Role</button>
                    </form>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection

@section('content')
@include('projects.partials.project-header')
@include('projects.partials.project-nav', ['currentView' => 'sharing'])

<div class="row">
    <div class="col-12 mb-3">
        <p class="lead text-muted mb-0">Control who can see this project</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        {{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        {{-- Explanation Card --}}
        <div class="card mb-4 bg-light">
            <div class="card-body">
                <h5 class="card-title">How project visibility and permissions work</h5>
                
                <div class="mb-3">
                    <h6 class="mb-2">Default Visibility</h6>
                    <ul class="mb-0">
                        <li><strong>Admins</strong> — See all projects automatically</li>
                        <li><strong>Team Members</strong> — See all projects automatically</li>
                        <li><strong>Clients</strong> — Only see projects you explicitly share with them</li>
                    </ul>
                </div>
                
                <div class="mb-0">
                    <h6 class="mb-2">What Each Role Can Do</h6>
                    <div class="row">
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Admins</strong></p>
                            <ul class="small text-muted mb-2">
                                <li>Full access to all features</li>
                                <li>Create, edit, delete all tasks</li>
                                <li>Delete any comment/attachment</li>
                                <li>Manage assignments & dates</li>
                                <li>Add/edit resources</li>
                                <li>Manage sharing & settings</li>
                                <li><strong>Plus admin-only:</strong></li>
                                <li style="margin-left: 1rem;">User management</li>
                                <li style="margin-left: 1rem;">Bulk operations</li>
                                <li style="margin-left: 1rem;">Activity logs</li>
                                <li style="margin-left: 1rem;">Filter by owner</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Team Members</strong></p>
                            <ul class="small text-muted mb-2">
                                <li>View all content</li>
                                <li>Create & edit all tasks</li>
                                <li>Delete own tasks only</li>
                                <li>Delete own comments/attachments</li>
                                <li>Manage assignments & dates</li>
                                <li>Add/edit resources</li>
                                <li>Manage sharing & settings</li>
                            </ul>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Clients</strong></p>
                            <ul class="small text-muted mb-2">
                                <li>View all content</li>
                                <li>Edit tasks & descriptions</li>
                                <li>Add comments & attachments</li>
                                <li>Add resources</li>
                                <li>View & edit due dates</li>
                                <li>Delete tasks they created</li>
                                <li><em>Cannot manage assignments</em></li>
                                <li><em>Cannot see/edit start dates</em></li>
                                <li><em>No access to Sharing or Settings</em></li>
                            </ul>
                        </div>
                    </div>
                </div>
                

            </div>
        </div>

        {{-- Current Shares --}}
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Who can see this project</h2>
            </div>
            <div class="card-body">
                @if($shares->isEmpty())
                    <p class="text-muted mb-0">
                        No explicit shares yet. All team members and admins can see this project by default.
                    </p>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($shares as $share)
                            <div class="list-group-item px-0">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        @if($share->isUserShare())
                                            <h6 class="mb-1">{{ $share->user->name ?? 'Unknown User' }}</h6>
                                            <small class="text-muted">Shared with user</small>
                                        @else
                                            <h6 class="mb-1">All {{ ucfirst($share->getRoleName()) }} users</h6>
                                            <small class="text-muted">Shared with role</small>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('projects.shares.destroy', [$project, $share->id]) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Remove this share?')">
                                            Remove
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        {{-- Share with User --}}
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="h5 mb-0">Share with a user</h3>
            </div>
            <div class="card-body">
                @if($availableUsers->isEmpty())
                    <p class="text-muted small mb-0">All users have been shared with.</p>
                @else
                    <form method="POST" action="{{ route('projects.shares.store', $project) }}">
                        @csrf
                        <input type="hidden" name="shareable_type" value="user">
                        
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Select user</label>
                            <select name="shareable_id" id="user_id" class="form-select @error('shareable_id') is-invalid @enderror" required>
                                <option value="">Choose a user...</option>
                                @foreach($availableUsers as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }}
                                        @if($user->isClient())
                                            (Client)
                                        @elseif($user->isAdmin())
                                            (Admin)
                                        @else
                                            (Team)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('shareable_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Share</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Share with Role --}}
        <div class="card">
            <div class="card-header">
                <h3 class="h5 mb-0">Share with a role</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Share with all users in a role. This affects current and future users.
                </p>
                
                @php
                    $availableRoles = [];
                    if (!in_array('client', $sharedRoles)) {
                        $availableRoles[] = ['value' => 'client', 'label' => 'All Clients'];
                    }
                    if (!in_array('team', $sharedRoles)) {
                        $availableRoles[] = ['value' => 'team', 'label' => 'All Team Members'];
                    }
                @endphp

                @if(empty($availableRoles))
                    <p class="text-muted small mb-0">All roles have been shared with.</p>
                @else
                    <form method="POST" action="{{ route('projects.shares.store', $project) }}">
                        @csrf
                        <input type="hidden" name="shareable_type" value="role">
                        
                        <div class="mb-3">
                            <label for="role_id" class="form-label">Select role</label>
                            <select name="shareable_id" id="role_id" class="form-select" required>
                                <option value="">Choose a role...</option>
                                @foreach($availableRoles as $role)
                                    <option value="{{ $role['value'] }}">{{ $role['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">Share with role</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
