@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1>Users</h1>
                <p class="text-muted mb-0">Manage team members</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary">Create User</a>
        </div>

        {{-- Filter Form --}}
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('users.index') }}" class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label for="role" class="form-label mb-0 text-nowrap">Role</label>
                        <select class="form-select form-select-sm" id="role" name="role" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="team" {{ request('role') === 'team' ? 'selected' : '' }}>Team</option>
                            <option value="client" {{ request('role') === 'client' ? 'selected' : '' }}>Client</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label for="status" class="form-label mb-0 text-nowrap">Status</label>
                        <select class="form-select form-select-sm" id="status" name="status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>NetID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Activity</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td class="fw-medium">
                                        <a href="{{ route('users.show', $user) }}" class="text-decoration-none">
                                            {{ $user->name }}
                                        </a>
                                    </td>
                                    <td class="font-monospace">{{ $user->netid }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge 
                                            @if($user->isAdmin()) bg-danger
                                            @elseif($user->isClient()) bg-info
                                            @else bg-secondary
                                            @endif">
                                            {{ ucfirst($user->role) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($user->isActive())
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-warning">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($user->most_recent_activity)
                                            <span class="text-muted" title="{{ $user->most_recent_activity->format('M j, Y g:i A') }}">
                                                {{ $user->most_recent_activity->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-muted fst-italic">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            {{ $user->created_at->format('M j, Y') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                                Edit
                                            </a>
                                            @if($user->id !== auth()->id())
                                                <form action="{{ route('users.destroy', $user) }}" method="POST" class="d-inline" 
                                                      onsubmit="return confirm('Are you sure you want to delete {{ $user->name }} ({{ $user->netid }})? This action cannot be undone and will remove all task assignments.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        Delete
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        No users found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
