@extends('layouts.app')

@section('title', 'Move Tasks from ' . $project->name)

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <a href="{{ route('projects.edit', $project) }}" class="text-decoration-none">&larr; Project settings</a>
        <h1 class="h3 mt-2">Move tasks to another project</h1>
        <p class="text-muted mb-0">Move every task from <strong>{{ $project->name }}</strong> into another project's board. The source project remains unless you choose to delete it below.</p>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Could not move the tasks.</strong>
            <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <p class="mb-4"><strong>{{ $taskCount }}</strong> {{ \Illuminate\Support\Str::plural('task', $taskCount) }} will be moved.</p>

            @if($targets->isEmpty())
                <div class="alert alert-info mb-0">There are no other projects available as a destination.</div>
            @else
                <form id="mergeTasksForm" method="POST" action="{{ route('projects.merge.store', $project) }}">
                    @csrf
                    <div class="mb-3">
                        <label for="target_project_id" class="form-label">Destination project</label>
                        <select class="form-select @error('target_project_id') is-invalid @enderror" id="target_project_id" name="target_project_id" required>
                            <option value="">Choose a project...</option>
                            @foreach($targets as $target)
                                <option value="{{ $target->id }}" @selected(old('target_project_id') == $target->id)>{{ $target->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="target_phase_id" class="form-label">Put all moved tasks in this phase <span class="text-muted">(optional)</span></label>
                        <select class="form-select @error('target_phase_id') is-invalid @enderror" id="target_phase_id" name="target_phase_id" disabled>
                            <option value="">No phase (leave tasks unassigned)</option>
                        </select>
                        <div class="form-text">When a phase is selected, every moved task will be assigned to it. Board columns with the same name are preserved; other tasks use the destination project's default column.</div>
                    </div>

                    <div class="card border-danger mb-4">
                        <div class="card-header bg-danger text-white">
                            <strong>Danger zone</strong>
                        </div>
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input border-danger" type="checkbox" value="1" id="delete_source_project" name="delete_source_project" @checked(old('delete_source_project'))>
                                <label class="form-check-label text-danger fw-bold" for="delete_source_project">
                                    Delete “{{ $project->name }}” after moving its tasks
                                </label>
                            </div>
                            <p class="text-danger small mt-2 mb-0">This permanently deletes the source project and its remaining project data, including phases, resources, shares, and project history. Moved tasks and their task history and attachments are kept.</p>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <button id="moveTasksSubmit" type="submit" class="btn btn-primary" @disabled($taskCount === 0 && !old('delete_source_project'))>Move {{ $taskCount }} {{ \Illuminate\Support\Str::plural('task', $taskCount) }}</button>
                        <a href="{{ route('projects.edit', $project) }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const projectSelect = document.getElementById('target_project_id');
    const phaseSelect = document.getElementById('target_phase_id');
    const deleteSourceCheckbox = document.getElementById('delete_source_project');
    const submitButton = document.getElementById('moveTasksSubmit');
    if (!projectSelect || !phaseSelect) return;

    const oldPhaseId = @json(old('target_phase_id'));
    const taskCount = @json($taskCount);
    const taskLabel = @json(\Illuminate\Support\Str::plural('task', $taskCount));
    const phaseUrlTemplate = @json(route('projects.merge-target-phases', ['project' => $project->id, 'target' => '__TARGET__']));
    const sourceName = @json($project->name);
    const loadPhases = async (projectId, selectedPhase = '') => {
        phaseSelect.innerHTML = '<option value="">No phase (leave tasks unassigned)</option>';
        phaseSelect.disabled = !projectId;
        if (!projectId) return;

        try {
            const response = await fetch(phaseUrlTemplate.replace('__TARGET__', encodeURIComponent(projectId)), {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) throw new Error('Unable to load phases');
            const phases = await response.json();
            phases.forEach(phase => {
                const option = document.createElement('option');
                option.value = phase.id;
                option.textContent = phase.name;
                option.selected = String(phase.id) === String(selectedPhase);
                phaseSelect.appendChild(option);
            });
            phaseSelect.disabled = false;
        } catch (error) {
            phaseSelect.innerHTML = '<option value="">Could not load phases</option>';
            phaseSelect.disabled = true;
        }
    };

    projectSelect.addEventListener('change', () => loadPhases(projectSelect.value));
    if (projectSelect.value) loadPhases(projectSelect.value, oldPhaseId);

    const updateSubmitButton = () => {
        submitButton.disabled = taskCount === 0 && !deleteSourceCheckbox.checked;
        if (taskCount === 0) {
            submitButton.textContent = deleteSourceCheckbox.checked ? 'Delete source project' : 'No tasks to move';
        } else {
            submitButton.textContent = deleteSourceCheckbox.checked
                ? `Move ${taskCount} ${taskLabel} and delete source project`
                : `Move ${taskCount} ${taskLabel}`;
        }
    };
    deleteSourceCheckbox.addEventListener('change', updateSubmitButton);
    updateSubmitButton();

    document.getElementById('mergeTasksForm').addEventListener('submit', event => {
        const targetName = projectSelect.options[projectSelect.selectedIndex]?.text;
        const confirmation = deleteSourceCheckbox.checked
            ? `Move all ${taskCount} tasks from “${sourceName}” to “${targetName}” and permanently delete the source project and its remaining data?`
            : `Move all ${taskCount} tasks from “${sourceName}” to “${targetName}”? The source project will remain.`;
        if (!confirm(confirmation)) {
            event.preventDefault();
        }
    });
});
</script>
@endpush
