@extends('layouts.app')

@section('title', 'NetID Login')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
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

                <div class="alert alert-info">
                    <strong>Note:</strong> Only authorized users with existing accounts can access Hacklog.
                    If you need access, please contact an administrator.
                </div>

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
    </div>
</div>
@endsection
