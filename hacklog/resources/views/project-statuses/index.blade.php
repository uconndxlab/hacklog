@extends('layouts.app')

@section('title', 'Project Statuses')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Project Statuses</h1>
        <small class="text-muted">Configure names, colors, ordering, and which statuses appear in day-to-day views.</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card mb-4">
    <div class="card-header bg-light"><h2 class="h6 mb-0">Add project status</h2></div>
    <div class="card-body">
        <form action="{{ route('project-statuses.store') }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-4">
                <label for="status-name" class="form-label">Name</label>
                <input id="status-name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="80">
            </div>
            <div class="col-md-3">
                <label for="status-color" class="form-label">Color</label>
                <input id="status-color" name="color" type="color" class="form-control form-control-color" value="{{ old('color', '#6c757d') }}" required>
            </div>
            <div class="col-md-2">
                <label for="status-position" class="form-label">Order</label>
                <input id="status-position" name="position" type="number" class="form-control" value="{{ old('position', ($statuses->max('position') ?? 0) + 10) }}" min="0" max="9999" required>
            </div>
            <div class="col-md-2">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" id="status-active-views" name="show_in_active_views" value="1" @checked(old('show_in_active_views', true))>
                    <label class="form-check-label" for="status-active-views">Active views</label>
                </div>
            </div>
            <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Status</th><th>Internal key</th><th>Active views</th><th>Projects</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @foreach($statuses as $status)
                        @php $formId = 'status-'.$status->id; @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="flex-shrink-0" style="width: 8rem;">
                                        <span class="badge d-inline-block text-truncate" style="max-width: 100%; background-color: {{ $status->color }}; color: {{ $status->textColor() }}" title="{{ $status->name }}">{{ $status->name }}</span>
                                    </div>
                                    <input form="{{ $formId }}" name="name" class="form-control form-control-sm flex-shrink-0" value="{{ $status->name }}" required maxlength="80" style="width: 16rem;">
                                    <input form="{{ $formId }}" name="color" type="color" class="form-control form-control-color form-control-sm" value="{{ $status->color }}" required>
                                    <input form="{{ $formId }}" name="position" type="number" class="form-control form-control-sm" value="{{ $status->position }}" min="0" max="9999" required style="width: 5rem;" title="Sort order">
                                </div>
                            </td>
                            <td><code>{{ $status->key }}</code>@if($status->is_system)<span class="badge bg-light text-dark border ms-1">Core</span>@endif</td>
                            <td>
                                <div class="form-check form-switch mb-0">
                                    <input form="{{ $formId }}" type="checkbox" class="form-check-input" name="show_in_active_views" value="1" @checked($status->show_in_active_views) aria-label="Show {{ $status->name }} in dashboards and active views">
                                </div>
                            </td>
                            <td>{{ $status->projects_count }}</td>
                            <td class="text-end text-nowrap">
                                <form id="{{ $formId }}" action="{{ route('project-statuses.update', $status) }}" method="POST" class="d-inline">
                                    @csrf @method('PUT')
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </form>
                                @unless($status->is_system)
                                    <form action="{{ route('project-statuses.destroy', $status) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this project status?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" @disabled($status->projects_count > 0)>Delete</button>
                                    </form>
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
<p class="form-text mt-2">Active views include personal and team dashboards and the default Projects list. Reports always include every status.</p>
@endsection
