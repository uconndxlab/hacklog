@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
        <li class="breadcrumb-item active" aria-current="page">Create User</li>
    </ol>
</nav>

<div class="row">
    <div class="col-lg-8">
        <div class="mb-4">
            <h1 class="mb-1">Create User</h1>
            <p class="text-muted mb-0">Add a new team member using their NetID</p>
        </div>

        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>There were some errors with your submission:</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('users.store') }}" method="POST" id="createUserForm">
                    @csrf

                    <div class="mb-3">
                        <label for="search" class="form-label">Search User</label>
                        <div class="input-group">
                            <input 
                                type="text" 
                                class="form-control" 
                                id="search" 
                                placeholder="Search by name or NetID"
                                autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="searchBtn">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </div>
                        <div class="form-text">Search by name or NetID to find users in the directory.</div>
                    </div>

                    <!-- Search results dropdown -->
                    <div id="searchResults" class="list-group mb-3 d-none" style="max-height: 400px; overflow-y: auto;">
                    </div>

                    <div class="mb-3">
                        <label for="netid_display" class="form-label">Selected NetID</label>
                        <input 
                            type="text" 
                            class="form-control @error('netid') is-invalid @enderror" 
                            id="netid_display" 
                            value="{{ old('netid') }}" 
                            readonly
                            placeholder="Search and select a user above">
                        <input type="hidden" id="netid" name="netid" value="{{ old('netid') }}" required>
                        @error('netid')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <!-- LDAP lookup result display -->
                    <div id="userPreview" class="alert alert-info d-none">
                        <h6>Directory Lookup Result:</h6>
                        <p class="mb-1"><strong>Name:</strong> <span id="previewName"></span></p>
                        <p class="mb-0"><strong>Email:</strong> <span id="previewEmail"></span></p>
                    </div>

                    <div id="lookupError" class="alert alert-warning d-none">
                        <strong>Warning:</strong> <span id="errorMessage"></span>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select 
                            class="form-select @error('role') is-invalid @enderror" 
                            id="role" 
                            name="role" 
                            required>
                            <option value="">Select Role</option>
                            <option value="user" {{ old('role') == 'user' ? 'selected' : '' }}>User</option>
                            <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin</option>
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3 form-check">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="active" 
                            name="active" 
                            value="1"
                            {{ old('active', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="active">
                            Active
                        </label>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            Create User
                        </button>
                        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <h6 class="card-title">How This Works</h6>
                <ul class="mb-0">
                    <li>Search for a user by name or NetID</li>
                    <li>Select the user from the search results</li>
                    <li>Their name and email will be automatically populated from the directory</li>
                    <li>Choose their role and activation status</li>
                    <li>The user will be able to log in using their NetID via CAS authentication</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    const searchBtn = document.getElementById('searchBtn');
    const searchResults = document.getElementById('searchResults');
    const netidInput = document.getElementById('netid');
    const netidDisplay = document.getElementById('netid_display');
    const submitBtn = document.getElementById('submitBtn');
    const userPreview = document.getElementById('userPreview');
    const lookupError = document.getElementById('lookupError');
    const previewName = document.getElementById('previewName');
    const previewEmail = document.getElementById('previewEmail');
    const errorMessage = document.getElementById('errorMessage');

    let selectedUser = null;

    // Search for users
    function performSearch() {
        const searchTerm = searchInput.value.trim();
        
        if (!searchTerm || searchTerm.length < 2) {
            searchResults.classList.add('d-none');
            return;
        }

        // Show loading state
        searchBtn.disabled = true;
        searchResults.innerHTML = '<div class="list-group-item">Searching...</div>';
        searchResults.classList.remove('d-none');

        // Make AJAX request to search users
        fetch('{{ route("users.search") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ search: searchTerm })
        })
        .then(response => response.json())
        .then(data => {
            if (data.results && data.results.length > 0) {
                // Display results
                searchResults.innerHTML = '';
                data.results.forEach(user => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.innerHTML = `
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${escapeHtml(user.name)}</h6>
                            <small class="text-muted">${escapeHtml(user.netid)}</small>
                        </div>
                        <small class="text-muted">${escapeHtml(user.email)}</small>
                    `;
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        selectUser(user);
                    });
                    searchResults.appendChild(item);
                });
            } else {
                searchResults.innerHTML = '<div class="list-group-item">No users found matching your search.</div>';
            }
        })
        .catch(error => {
            searchResults.innerHTML = '<div class="list-group-item text-danger">Error searching for users. Please try again.</div>';
        })
        .finally(() => {
            searchBtn.disabled = false;
        });
    }

    // Select a user from search results
    function selectUser(user) {
        selectedUser = user;
        netidInput.value = user.netid;
        netidDisplay.value = user.netid;
        previewName.textContent = user.name;
        previewEmail.textContent = user.email;
        userPreview.classList.remove('d-none');
        lookupError.classList.add('d-none');
        searchResults.classList.add('d-none');
        submitBtn.disabled = false;
        searchInput.value = ''; // Clear search
    }

    // Helper function to escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Search button click
    searchBtn.addEventListener('click', performSearch);

    // Search on Enter key
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performSearch();
        }
    });

    // Hide search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target) && !searchBtn.contains(e.target)) {
            searchResults.classList.add('d-none');
        }
    });

    // Reset selection when netid is cleared
    netidInput.addEventListener('input', function() {
        if (!netidInput.value.trim()) {
            selectedUser = null;
            netidDisplay.value = '';
            userPreview.classList.add('d-none');
            lookupError.classList.add('d-none');
            submitBtn.disabled = true;
        }
    });

    // Prevent form submission without selecting a user
    document.getElementById('createUserForm').addEventListener('submit', function(e) {
        if (!netidInput.value || !netidInput.value.trim()) {
            e.preventDefault();
            alert('Please search for and select a user before creating.');
            return false;
        }
    });
});
</script>
@endsection
