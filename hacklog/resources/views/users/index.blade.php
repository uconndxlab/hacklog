@extends('layouts.app')

@section('title', 'Users')

@section('content')
<div class="row">
    <div class="col-lg-10">
        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1>Users</h1>
                <p class="text-muted mb-0">Manage team members</p>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary">Create User</a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td class="fw-medium">{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge {{ $user->isAdmin() ? 'bg-danger' : 'bg-secondary' }}">
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
                                        <span class="text-muted">
                                            {{ $user->created_at->format('M j, Y') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                            Edit
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
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
