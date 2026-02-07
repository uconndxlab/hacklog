<tr id="column-{{ $column->id }}">
    <td>{{ $column->position }}</td>
    <td>{{ $column->name }}</td>
    <td>
        @if($column->is_default)
            <span class="badge bg-info">Default</span>
        @endif
    </td>
    <td class="text-end">
        <button 
            class="btn btn-sm btn-outline-secondary"
            hx-get="{{ route('projects.columns.edit', [$project, $column]) }}"
            hx-target="#column-{{ $column->id }}"
            hx-swap="outerHTML">
            Edit
        </button>
        <button 
            class="btn btn-sm btn-outline-danger"
            hx-delete="{{ route('projects.columns.destroy', [$project, $column]) }}"
            hx-target="#column-{{ $column->id }}"
            hx-swap="outerHTML"
            hx-confirm="Are you sure you want to delete this column?">
            Delete
        </button>
    </td>
</tr>
