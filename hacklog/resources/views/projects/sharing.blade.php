@extends('layouts.app')

@section('title', $project->name . ' - Sharing & Access')

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
                <h5 class="card-title">How project visibility works</h5>
                <ul class="mb-0">
                    <li><strong>Team members</strong> can see all projects by default</li>
                    <li><strong>Admins</strong> have full access to everything</li>
                    <li><strong>Clients</strong> only see projects you explicitly share with them</li>
                </ul>
                <p class="mb-0 mt-3 text-muted small">
                    Sharing a project grants visibility only. To control what someone can do within a project,
                    assign them a role in <a href="{{ route('projects.resources.index', $project) }}">Resources</a>.
                </p>
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
