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

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select 
                            class="form-select @error('role') is-invalid @enderror" 
                            id="role" 
                            name="role" 
                            required>
                            <option value="user" {{ old('role', $user->role) == 'user' ? 'selected' : '' }}>User</option>
                            <option value="admin" {{ old('role', $user->role) == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
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
    </div>
</div>
@endsection
