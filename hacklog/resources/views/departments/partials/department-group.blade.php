<tbody id="department-group-{{ $department->id }}">
    @php $homeFormId = 'edit-department-'.$department->id; @endphp
    <tr data-inline-edit>
        <td>
            <div class="inventory-inline-name">
                <span data-inline-display class="fw-semibold">{{ $department->name }}</span>
                <input
                    form="{{ $homeFormId }}"
                    type="text"
                    class="form-control form-control-sm"
                    data-inline-input
                    name="name"
                    value="{{ $department->name }}"
                    required>
            </div>
        </td>
        <td class="col-projects">{{ $department->projects_count }}</td>
        <td class="col-actions text-end text-nowrap">
            <form
                id="{{ $homeFormId }}"
                action="{{ route('departments.update', $department) }}"
                method="POST"
                class="d-none"
                hx-put="{{ route('departments.update', $department) }}"
                hx-target="closest tbody"
                hx-swap="outerHTML">
                @csrf
                @method('PUT')
            </form>
            <span class="inventory-inline-toggle">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-inline-start aria-label="Edit" title="Edit">
                    @include('partials.pencil-icon')
                </button>
                <button type="submit" form="{{ $homeFormId }}" class="btn btn-sm btn-primary" data-inline-save>Save</button>
            </span>
            <form
                action="{{ route('departments.destroy', $department) }}"
                method="POST"
                class="d-inline"
                hx-delete="{{ route('departments.destroy', $department) }}"
                hx-target="closest tbody"
                hx-swap="delete"
                hx-confirm="Delete this home department and its nested departments?">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
        </td>
    </tr>

    @foreach($department->children as $nested)
        @php $nestedFormId = 'edit-nested-'.$nested->id; @endphp
        <tr class="table-light" data-inline-edit>
            <td class="ps-5">
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted">↳</span>
                    <div class="inventory-inline-name">
                        <span data-inline-display>{{ $nested->name }}</span>
                        <input
                            form="{{ $nestedFormId }}"
                            type="text"
                            class="form-control form-control-sm"
                            data-inline-input
                            name="name"
                            value="{{ $nested->name }}"
                            required>
                    </div>
                </div>
            </td>
            <td class="col-projects">{{ $nested->nested_projects_count }}</td>
            <td class="col-actions text-end text-nowrap">
                <form
                    id="{{ $nestedFormId }}"
                    action="{{ route('departments.nested.update', [$department, $nested]) }}"
                    method="POST"
                    class="d-none"
                    hx-put="{{ route('departments.nested.update', [$department, $nested]) }}"
                    hx-target="closest tbody"
                    hx-swap="outerHTML">
                    @csrf
                    @method('PUT')
                </form>
                <span class="inventory-inline-toggle">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-inline-start aria-label="Edit" title="Edit">
                        @include('partials.pencil-icon')
                    </button>
                    <button type="submit" form="{{ $nestedFormId }}" class="btn btn-sm btn-primary" data-inline-save>Save</button>
                </span>
                <form
                    action="{{ route('departments.nested.destroy', [$department, $nested]) }}"
                    method="POST"
                    class="d-inline"
                    hx-delete="{{ route('departments.nested.destroy', [$department, $nested]) }}"
                    hx-target="closest tr"
                    hx-swap="delete"
                    hx-confirm="Delete this nested department?">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
            </td>
        </tr>
    @endforeach

    <tr class="table-light">
        <td colspan="3" class="ps-5 pb-3">
            <form
                action="{{ route('departments.nested.store', $department) }}"
                method="POST"
                class="d-flex gap-2 align-items-center"
                style="max-width: 480px;"
                hx-post="{{ route('departments.nested.store', $department) }}"
                hx-target="closest tbody"
                hx-swap="outerHTML">
                @csrf
                <input
                    type="text"
                    class="form-control form-control-sm"
                    name="name"
                    placeholder="Add nested department under {{ $department->name }}"
                    required>
                <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">Add nested</button>
            </form>
        </td>
    </tr>
</tbody>
