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
    <div class="col-lg-6">
        <div class="mb-4">
            <h1 class="mb-1">Edit User</h1>
            <p class="text-muted mb-0">Update user information</p>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{ route('users.update', $user) }}" method="POST">
                    @csrf
                    @method('PUT')

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
                        <label for="role" class="form-label">Role</label>
                        <select 
                            class="form-select @error('role') is-invalid @enderror" 
                            id="role" 
                            name="role" 
                            required>
                            <option value="user" {{ old('role', $user->role) === 'user' ? 'selected' : '' }}>User</option>
                            <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Admins can manage users and have full access</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="active" 
                            name="active" 
                            {{ old('active', $user->active) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            Active (user can log in)
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Update User</button>
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="mt-4">
            <div class="card border-light bg-light">
                <div class="card-body">
                    <h2 class="h6 mb-2">User Information</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8">{{ $user->created_at->format('F j, Y \a\t g:i A') }}</dd>

                        <dt class="col-sm-4">Last Updated</dt>
                        <dd class="col-sm-8 mb-0">{{ $user->updated_at->format('F j, Y \a\t g:i A') }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
