@php $officeFormId = 'edit-office-'.$office->id; @endphp
<tr data-inline-edit>
    <td>
        <div class="inventory-inline-name">
            <span data-inline-display>{{ $office->name }}</span>
            <input
                form="{{ $officeFormId }}"
                type="text"
                class="form-control form-control-sm"
                data-inline-input
                name="name"
                value="{{ $office->name }}"
                required>
        </div>
    </td>
    <td class="col-projects">{{ $office->projects_count }}</td>
    <td class="col-actions text-end text-nowrap">
        <form
            id="{{ $officeFormId }}"
            action="{{ route('major-offices.update', $office) }}"
            method="POST"
            class="d-none"
            hx-put="{{ route('major-offices.update', $office) }}"
            hx-target="closest tr"
            hx-swap="outerHTML">
            @csrf
            @method('PUT')
        </form>
        <span class="inventory-inline-toggle">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-inline-start aria-label="Edit" title="Edit">
                @include('partials.pencil-icon')
            </button>
            <button type="submit" form="{{ $officeFormId }}" class="btn btn-sm btn-primary" data-inline-save>Save</button>
        </span>
        <form
            action="{{ route('major-offices.destroy', $office) }}"
            method="POST"
            class="d-inline"
            hx-delete="{{ route('major-offices.destroy', $office) }}"
            hx-target="closest tr"
            hx-swap="delete"
            hx-confirm="Delete this school or major office?">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
        </form>
    </td>
</tr>
