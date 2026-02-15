@extends('layouts.app')

@section('title', 'Edit User - ' . $user->name)

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $user->name }}</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="mb-4">
            <h1 class="mb-1">Edit User</h1>
            <p class="text-muted mb-0">Update user role and status</p>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('users.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @if(config('hacklog_auth.driver') === 'cas')
                        {{-- CAS Authentication: Show NetID --}}
                        <div class="mb-3">
                            <label class="form-label">NetID</label>
                            <input 
                                type="text" 
                                class="form-control" 
                                value="{{ $user->netid }}" 
                                readonly>
                            <div class="form-text">NetID cannot be changed</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <div class="input-group">
                                <input 
                                    type="text" 
                                    class="form-control" 
                                    value="{{ $user->name }}" 
                                    readonly>
                                <button type="submit" name="refresh_ldap" value="1" class="btn btn-outline-secondary">
                                    Refresh from Directory
                                </button>
                            </div>
                            <div class="form-text">Name is managed via directory lookup</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input 
                                type="email" 
                                class="form-control" 
                                value="{{ $user->email }}" 
                                readonly>
                            <div class="form-text">Email is managed via directory lookup</div>
                        </div>
                    @else
                        {{-- Local Authentication: Editable fields --}}
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input 
                                type="text" 
                                class="form-control @error('name') is-invalid @enderror" 
                                id="name" 
                                name="name" 
                                value="{{ old('name', $user->name) }}" 
                                required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input 
                                type="email" 
                                class="form-control @error('email') is-invalid @enderror" 
                                id="email" 
                                name="email" 
                                value="{{ old('email', $user->email) }}" 
                                required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input 
                                type="password" 
                                class="form-control @error('password') is-invalid @enderror" 
                                id="password" 
                                name="password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">Leave blank to keep current password. Minimum 8 characters if changing.</div>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password_confirmation" 
                                name="password_confirmation">
                        </div>
                    @endif

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select 
                            class="form-select @error('role') is-invalid @enderror" 
                            id="role" 
                            name="role" 
                            required>
                            <option value="team" {{ old('role', $user->role) == 'team' ? 'selected' : '' }}>Team (Internal staff, sees all projects)</option>
                            <option value="client" {{ old('role', $user->role) == 'client' ? 'selected' : '' }}>Client (External user, sees only shared projects)</option>
                            <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Admin (Full system access)</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Role determines default project visibility</div>
                    </div>

                    <div class="mb-4 form-check">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="active" 
                            name="active" 
                            {{ old('active', $user->active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            Active
                        </label>
                        <div class="form-text">Inactive users cannot log in</div>
                    </div>

                    @error('refresh_ldap')
                        <div class="alert alert-warning">{{ $message }}</div>
                    @enderror

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            Update User
                        </button>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        @if(config('hacklog_auth.driver') === 'cas')
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">Directory Integration</h6>
                    <p class="mb-0">
                        Name and email are automatically managed through the University directory.
                        Click "Refresh from Directory" to update these fields with the latest information.
                        Only role and active status can be changed manually.
                    </p>
                </div>
            </div>
        @else
            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">User Management</h6>
                    <p class="mb-0">
                        Update the user's information, role, and active status.
                        Leave the password fields blank to keep the current password.
                    </p>
                </div>
            </div>
        @endif

    </div>
</div>
@endsection