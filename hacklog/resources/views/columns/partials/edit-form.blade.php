<tr id="column-{{ $column->id }}">
    <td colspan="4">
        <form 
            hx-put="{{ route('projects.columns.update', [$project, $column]) }}"
            hx-target="#column-{{ $column->id }}"
            hx-swap="outerHTML"
            class="row g-2 align-items-end">
            @csrf
            @method('PUT')

            <div class="col-md-2">
                <label for="position-{{ $column->id }}" class="form-label small mb-1">Position</label>
                <input 
                    type="number" 
                    class="form-control form-control-sm" 
                    id="position-{{ $column->id }}" 
                    name="position" 
                    value="{{ $column->position }}"
                    required
                    min="0">
            </div>

            <div class="col-md-5">
                <label for="name-{{ $column->id }}" class="form-label small mb-1">Name</label>
                <input 
                    type="text" 
                    class="form-control form-control-sm" 
                    id="name-{{ $column->id }}" 
                    name="name" 
                    value="{{ $column->name }}"
                    required>
            </div>

            <div class="col-md-2">
                <div class="form-check">
                    <input 
                        type="checkbox" 
                        class="form-check-input" 
                        id="is_default-{{ $column->id }}" 
                        name="is_default"
                        value="1"
                        {{ $column->is_default ? 'checked' : '' }}>
                    <label class="form-check-label small" for="is_default-{{ $column->id }}">
                        Default
                    </label>
                </div>
            </div>

            <div class="col-md-3 text-end">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button 
                    type="button" 
                    class="btn btn-secondary btn-sm"
                    hx-get="{{ route('projects.columns.index', $project) }}"
                    hx-target="#column-{{ $column->id }}"
                    hx-swap="outerHTML"
                    hx-select="#column-{{ $column->id }}">
                    Cancel
                </button>
            </div>
        </form>
    </td>
</tr>
