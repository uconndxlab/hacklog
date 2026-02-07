<div class="card">
    <div class="card-body">
        <h5 class="card-title">Create Column</h5>
        <form 
            hx-post="{{ route('projects.columns.store', $project) }}"
            hx-target="#columns-list"
            hx-swap="beforeend"
            hx-on::after-request="if(event.detail.successful) { this.closest('#create-form-container').innerHTML = ''; document.getElementById('empty-state')?.remove(); }">
            @csrf

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="position" class="form-label">Position</label>
                    <input 
                        type="number" 
                        class="form-control" 
                        id="position" 
                        name="position" 
                        value="{{ $project->columns->max('position') + 1 ?? 0 }}"
                        required
                        min="0">
                    <div class="form-text">Lower numbers appear first</div>
                </div>

                <div class="col-md-6 mb-3">
                    <label for="name" class="form-label">Name</label>
                    <input 
                        type="text" 
                        class="form-control" 
                        id="name" 
                        name="name" 
                        required>
                </div>

                <div class="col-md-2 mb-3">
                    <label class="form-label d-block">&nbsp;</label>
                    <div class="form-check">
                        <input 
                            type="checkbox" 
                            class="form-check-input" 
                            id="is_default" 
                            name="is_default"
                            value="1">
                        <label class="form-check-label" for="is_default">
                            Default
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Create</button>
                <button 
                    type="button" 
                    class="btn btn-secondary btn-sm"
                    onclick="this.closest('#create-form-container').innerHTML = ''">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
