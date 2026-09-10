@extends('layouts.app')

@section('title', 'Project Types')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Project Types</h1>
        <small class="text-muted">Configure the names and ordering used in project forms and reports.</small>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="card mb-4">
    <div class="card-header bg-light"><h2 class="h6 mb-0">Add project type</h2></div>
    <div class="card-body">
        <form action="{{ route('project-types.store') }}" method="POST" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-7">
                <label for="type-name" class="form-label">Name</label>
                <input id="type-name" name="name" class="form-control" value="{{ old('name') }}" required maxlength="80">
            </div>
            <div class="col-md-3">
                <label for="type-position" class="form-label">Order</label>
                <input id="type-position" name="position" type="number" class="form-control" value="{{ old('position', ($types->max('position') ?? 0) + 10) }}" min="0" max="9999" required>
            </div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Type</th><th>Internal key</th><th>Projects</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @foreach($types as $type)
                        @php $formId = 'type-'.$type->id; @endphp
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <input form="{{ $formId }}" name="name" class="form-control form-control-sm" value="{{ $type->name }}" required maxlength="80" style="width: 20rem;">
                                    <input form="{{ $formId }}" name="position" type="number" class="form-control form-control-sm" value="{{ $type->position }}" min="0" max="9999" required style="width: 5rem;" title="Sort order">
                                </div>
                            </td>
                            <td><code>{{ $type->key }}</code></td>
                            <td>{{ $type->projects_count }}</td>
                            <td class="text-end text-nowrap">
                                <form id="{{ $formId }}" action="{{ route('project-types.update', $type) }}" method="POST" class="d-inline">
                                    @csrf @method('PUT')
                                    <button class="btn btn-sm btn-primary">Save</button>
                                </form>
                                <form action="{{ route('project-types.destroy', $type) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this project type?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" @disabled($type->projects_count > 0)>Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
