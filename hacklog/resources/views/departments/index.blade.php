@extends('layouts.app')

@section('title', 'Departments')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Departments</h1>
        <small class="text-muted">Add and remove departments. Each can have nested departments used on projects.</small>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light">
        <h2 class="h6 mb-0">Add department</h2>
    </div>
    <div class="card-body">
        <form
            action="{{ route('departments.store') }}"
            method="POST"
            class="row g-2 align-items-end"
            hx-post="{{ route('departments.store') }}"
            hx-target="#departments-table"
            hx-swap="beforeend"
            hx-on::after-request="if(event.detail.xhr.status >= 200 && event.detail.xhr.status < 300) this.reset()">
            @csrf
            <div class="col-md-8">
                <label for="name" class="form-label">Name</label>
                <input
                    type="text"
                    class="form-control @error('name') is-invalid @enderror"
                    id="name"
                    name="name"
                    value="{{ old('name') }}"
                    required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Add Department</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <label for="department-search" class="visually-hidden">Search departments</label>
        <input
            type="search"
            id="department-search"
            class="form-control"
            placeholder="Search departments…"
            data-inventory-search="#departments-table"
            data-inventory-search-mode="groups"
            autocomplete="off">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table id="departments-table" class="table table-hover mb-0 align-middle inventory-table">
                <thead>
                    <tr>
                        <th scope="col">Department</th>
                        <th scope="col" class="col-projects">Projects</th>
                        <th scope="col" class="col-actions text-end">Actions</th>
                    </tr>
                </thead>
                @foreach($departments as $department)
                    @include('departments.partials.department-group')
                @endforeach
            </table>
            <p class="text-muted small text-center py-3 mb-0 d-none" data-inventory-search-empty>No matching departments.</p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    @include('partials.inventory-inline-edit-script')
@endpush
