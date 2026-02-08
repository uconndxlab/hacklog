{{-- Task creation/edit form for board modal --}}
@php
    $isEdit = isset($task);
    $isGlobalModal = request()->query('global_modal') === '1';
    $formAction = $isEdit 
        ? route('projects.board.tasks.update', [$project, $task])
        : route('projects.board.tasks.store', $project);
    $formMethod = $isEdit ? 'PUT' : 'POST';
    $htmxMethod = $isEdit ? 'hx-put' : 'hx-post';
    
    if ($isGlobalModal) {
        $htmxTarget = '#taskCreationModalContent';
        $htmxSwap = 'innerHTML';
        $htmxSuccess = 'closeModal';
    } else {
        $targetColumnId = $isEdit ? $task->column_id : $columnId;
        $htmxTarget = "#board-column-{$targetColumnId}-tasks";
        $htmxSwap = 'outerHTML';
        $htmxSuccess = '';
    }
    
    // Determine current phase from request or task
    $currentPhaseId = old('phase_id', $isEdit ? $task->phase_id : request()->query('phase'));
    
    // If no phase selected, pick first active or first available
    if (!$currentPhaseId) {
        $currentPhaseId = $phases->where('status', 'active')->first()?->id ?? $phases->first()?->id;
    }
    
    // Get current phase for date display
    $currentPhase = $phases->firstWhere('id', $currentPhaseId);
    
    // Determine if we should hide phase selector (only one phase exists)
    $singlePhase = $phases->count() === 1;
    
    // Determine default status from column name
    $defaultStatus = 'planned';
    if (!$isEdit && isset($columnId)) {
        $column = $project->columns->firstWhere('id', $columnId);
        if ($column) {
            $columnName = strtolower($column->name);
            if (str_contains($columnName, 'progress') || str_contains($columnName, 'doing')) {
                $defaultStatus = 'active';
            } elseif (str_contains($columnName, 'done') || str_contains($columnName, 'complete')) {
                $defaultStatus = 'completed';
            }
        }
    }
@endphp

<form 
    action="{{ $formAction }}" 
    method="POST"
    {{ $htmxMethod }}="{{ $formAction }}"
    hx-target="{{ $htmxTarget }}"
    hx-swap="{{ $htmxSwap }}"
    @if($htmxSuccess) hx-on:htmx:after-request="{{ $htmxSuccess }}" @endif
    id="taskForm"
    style="display: flex; flex-direction: column; height: 100%;">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif
    
    <input type="hidden" name="column_id" value="{{ $isEdit ? $task->column_id : $columnId }}">
    <input type="hidden" name="from_board_modal" value="1">
    @if($isGlobalModal)
        <input type="hidden" name="global_modal" value="1">
    @endif
    
    {{-- Sticky header with actions --}}
    <div class="border-bottom bg-light px-3 py-2" style="position: sticky; top: 0; z-index: 10; flex-shrink: 0;">
        @if($isGlobalModal)
            <div class="mb-2">
                <small class="text-muted">Project: <strong>{{ $project->name }}</strong></small>
            </div>
        @endif
        <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm btn-primary">
                {{ $isEdit ? 'Update Task' : 'Create Task' }}
            </button>
        </div>
    </div>
    
    {{-- Scrollable body --}}
    <div style="flex: 1; overflow-y: auto; padding: 1rem 1.5rem;">
        
        {{-- Phase selector (hidden if only one phase) --}}
        @if($singlePhase)
            <input type="hidden" name="phase_id" value="{{ $phases->first()->id }}">
        @else
            <div class="mb-3">
                <label for="phase_id" class="form-label fw-semibold">Phase <span class="text-muted fw-normal">(optional)</span></label>
                <select
                    class="form-select @error('phase_id') is-invalid @enderror"
                    id="phase_id"
                    name="phase_id">
                    <option value="">None</option>
                    @foreach($phases as $phase)
                        <option value="{{ $phase->id }}"
                            data-start="{{ $phase->start_date?->format('M j, Y') }}"
                            data-end="{{ $phase->end_date?->format('M j, Y') }}"
                            {{ $currentPhaseId == $phase->id ? 'selected' : '' }}>
                            {{ $phase->name }}
                            @if($phase->status === 'active') • Active @endif
                        </option>
                    @endforeach
                </select>
                @if($currentPhase && ($currentPhase->start_date || $currentPhase->end_date))
                    <div class="text-muted small mt-1" id="phaseDateRange">
                        @if($currentPhase->start_date && $currentPhase->end_date)
                            {{ $currentPhase->start_date->format('M j') }} – {{ $currentPhase->end_date->format('M j, Y') }}
                        @elseif($currentPhase->start_date)
                            Starts {{ $currentPhase->start_date->format('M j, Y') }}
                        @elseif($currentPhase->end_date)
                            Due {{ $currentPhase->end_date->format('M j, Y') }}
                        @endif
                    </div>
                @endif
                @error('phase_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif
        
        {{-- Task title --}}
        <div class="mb-3">
            <label for="title" class="form-label fw-semibold">Title</label>
            <input 
                type="text" 
                class="form-control @error('title') is-invalid @enderror" 
                id="title" 
                name="title" 
                value="{{ old('title', $isEdit ? $task->title : '') }}" 
                placeholder="e.g., Update user profile endpoint"
                required>
            @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

                {{-- Status (less prominent) --}}
        <div class="mb-3">
            <label for="status" class="form-label text-muted small">Status</label>
            <select 
                class="form-select form-select-sm @error('status') is-invalid @enderror" 
                id="status" 
                name="status" 
                required>
                <option value="planned" {{ old('status', $isEdit ? $task->status : $defaultStatus) === 'planned' ? 'selected' : '' }}>Planned</option>
                <option value="active" {{ old('status', $isEdit ? $task->status : $defaultStatus) === 'active' ? 'selected' : '' }}>Active</option>
                <option value="completed" {{ old('status', $isEdit ? $task->status : $defaultStatus) === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
            @error('status')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

                @if($isEdit)
            <div class="mb-3">
                <label for="column_id_select" class="form-label text-muted small">Column</label>
                <select 
                    class="form-select form-select-sm @error('column_id') is-invalid @enderror" 
                    id="column_id_select" 
                    name="column_id" 
                    required>
                    @foreach($columns as $col)
                        <option value="{{ $col->id }}" {{ $task->column_id == $col->id ? 'selected' : '' }}>
                            {{ $col->name }}
                        </option>
                    @endforeach
                </select>
                @error('column_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        @endif

        {{-- Description editor --}}
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <input 
                id="description" 
                type="hidden" 
                name="description" 
                value="{{ old('description', $isEdit ? $task->description : '') }}">
            <trix-editor 
                input="description" 
                class="@error('description') is-invalid @enderror"
                style="min-height: 120px;"></trix-editor>
            @error('description')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        {{-- Dates --}}
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input 
                    type="date" 
                    class="form-control form-control-sm @error('start_date') is-invalid @enderror" 
                    id="start_date" 
                    name="start_date" 
                    value="{{ old('start_date', $isEdit && $task->start_date ? $task->start_date->format('Y-m-d') : '') }}">
                @error('start_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label for="due_date" class="form-label">Due Date</label>
                <input 
                    type="date" 
                    class="form-control form-control-sm @error('due_date') is-invalid @enderror" 
                    id="due_date" 
                    name="due_date" 
                    value="{{ old('due_date', $isEdit && $task->due_date ? $task->due_date->format('Y-m-d') : '') }}">
                @error('due_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        
        @if(!$isEdit || (!$task->start_date && !$task->due_date))
            <div class="text-muted small mb-3" style="margin-top: -0.5rem;">
                If no dates are set, this task will inherit the phase's date range.
            </div>
        @endif

        {{-- Assignment --}}
        <div class="mb-3">
            <label class="form-label">Assign To</label>
            
            {{-- Assigned users as pills --}}
            @php
                $selectedUserIds = old('assignees', $isEdit ? $task->users->pluck('id')->toArray() : []);
                $selectedUsers = $users->whereIn('id', $selectedUserIds);
            @endphp
            
            @if($selectedUsers->isNotEmpty())
                <div class="mb-2 d-flex flex-wrap gap-1" id="assignedUserPills">
                    @foreach($selectedUsers as $user)
                        <span class="badge bg-light text-dark border" style="font-weight: 400;">
                            {{ $user->name }}
                        </span>
                    @endforeach
                </div>
            @endif
            
            @include('partials.user-picker', [
                'users' => $users,
                'selectedUserIds' => $selectedUserIds,
                'inputName' => 'assignees[]'
            ])
            @error('assignees')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>




    </div>
</form>

<script>
// Phase selector date range display
(function() {
    const phaseSelect = document.getElementById('phase_id');
    const dateRangeDiv = document.getElementById('phaseDateRange');
    
    if (phaseSelect && dateRangeDiv) {
        phaseSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const startDate = selectedOption.getAttribute('data-start');
            const endDate = selectedOption.getAttribute('data-end');
            
            if (startDate && endDate) {
                dateRangeDiv.textContent = startDate + ' – ' + endDate;
                dateRangeDiv.style.display = 'block';
            } else if (startDate) {
                dateRangeDiv.textContent = 'Starts ' + startDate;
                dateRangeDiv.style.display = 'block';
            } else if (endDate) {
                dateRangeDiv.textContent = 'Due ' + endDate;
                dateRangeDiv.style.display = 'block';
            } else {
                dateRangeDiv.style.display = 'none';
            }
        });
    }
})();

// Keyboard shortcuts
(function() {
    const form = document.getElementById('taskForm');
    if (!form) return;
    
    // Cmd/Ctrl + Enter to submit
    form.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        }
    });
})();

// Update assigned user pills when checkboxes change
(function() {
    const form = document.getElementById('taskForm');
    if (!form) return;
    
    // Use event delegation to avoid duplicate event listeners
    form.addEventListener('change', function(e) {
        if (e.target.matches('input[name="assignees[]"]')) {
            updatePills();
        }
    });
    
    function updatePills() {
        const checkboxes = form.querySelectorAll('input[name="assignees[]"]');
        const checkedBoxes = Array.from(checkboxes).filter(cb => cb.checked);
        let pillsContainer = document.getElementById('assignedUserPills');
        
        if (checkedBoxes.length === 0) {
            if (pillsContainer) pillsContainer.style.display = 'none';
            return;
        }
        
        if (!pillsContainer) {
            // Create pills container if it doesn't exist
            const label = form.querySelector('label:has(+ #assignedUserPills), label:has(+ .user-picker)');
            if (!label) return;
            
            const newContainer = document.createElement('div');
            newContainer.id = 'assignedUserPills';
            newContainer.className = 'mb-2 d-flex flex-wrap gap-1';
            label.parentNode.insertBefore(newContainer, label.nextSibling);
            pillsContainer = newContainer;
        }
        
        pillsContainer.innerHTML = '';
        pillsContainer.style.display = 'flex';
        
        checkedBoxes.forEach(checkbox => {
            const label = form.querySelector(`label[for="${checkbox.id}"]`);
            if (!label) return;
            
            const pill = document.createElement('span');
            pill.className = 'badge bg-light text-dark border d-inline-flex align-items-center gap-1';
            pill.style.fontWeight = '400';
            
            // Add user name text
            const textNode = document.createTextNode(label.textContent.trim());
            pill.appendChild(textNode);
            
            // Add remove icon (only this is clickable)
            const removeIcon = document.createElement('span');
            removeIcon.textContent = '×';
            removeIcon.style.fontSize = '1.1em';
            removeIcon.style.fontWeight = 'bold';
            removeIcon.style.opacity = '0.7';
            removeIcon.style.marginLeft = '0.25rem';
            removeIcon.style.cursor = 'pointer';
            removeIcon.title = 'Remove assignment';
            
            // Add click handler to the remove icon only
            removeIcon.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent event bubbling
                checkbox.checked = false;
                // Remove this pill directly
                pill.remove();
                // Update the pills display (hide container if no pills left)
                const pillsContainer = document.getElementById('assignedUserPills');
                if (pillsContainer && pillsContainer.children.length === 0) {
                    pillsContainer.style.display = 'none';
                }
            });
            
            pill.appendChild(removeIcon);
            pillsContainer.appendChild(pill);
        });
    }
    
    // Initialize pills on page load
    updatePills();
})();

// Update modal title for global modal
@if($isGlobalModal)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modalTitle = document.getElementById('taskCreationModalLabel');
        if (modalTitle) {
            modalTitle.textContent = '{{ $isEdit ? "Edit Task" : "Create Task" }} in {{ $project->name }}';
        }
    });
</script>
@endif
