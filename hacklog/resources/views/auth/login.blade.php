@extends('layouts.app')

@section('title', config('hacklog_auth.driver') === 'cas' ? 'NetID Login' : 'Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        @if(config('hacklog_auth.driver') === 'cas')
            {{-- CAS Login --}}
            <div class="mb-4">
                <h1 class="mb-1">NetID Login</h1>
                <p class="text-muted">Sign in with your University NetID</p>
            </div>

            @if(session('error'))
                <div class="alert alert-danger" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('message'))
                <div class="alert alert-info" role="alert">
                    {{ session('message') }}
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="card-title">How to Log In</h5>
                        <ol class="mb-0">
                            <li>Click the "Log in with NetID" button below</li>
                            <li>You will be redirected to the University login page</li>
                            <li>Enter your NetID and password</li>
                            <li>You will be returned to Hacklog automatically</li>
                        </ol>
                    </div>

                    @if(!config('hacklog_auth.cas_auto_create_users'))
                        <div class="alert alert-info">
                            <strong>Note:</strong> Only authorized users with existing accounts can access Hacklog.
                            If you need access, please contact an administrator.
                        </div>
                    @endif

                    <div class="d-grid">
                        <a href="{{ route('login.cas') }}" class="btn btn-primary btn-lg">
                            Log in with NetID
                        </a>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-body">
                    <h6 class="card-title">Need Help?</h6>
                    <p class="card-text mb-0">
                        If you're having trouble logging in or need access to Hacklog,
                        please contact the system administrator.
                    </p>
                </div>
            </div>
        @else
            {{-- Local Login --}}
            <div class="mb-4">
                <h1 class="mb-1">Login</h1>
                <p class="text-muted">Sign in to your account</p>
            </div>

            @if(session('error'))
                <div class="alert alert-danger" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email') }}" 
                                   required 
                                   autofocus>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">
                                Remember me
                            </label>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                Login
                            </button>
                        </div>

                        <div class="text-center">
                            <a href="{{ route('password.request') }}" class="text-decoration-none">
                                Forgot your password?
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
