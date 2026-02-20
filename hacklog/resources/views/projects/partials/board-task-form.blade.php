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
        $htmxSuccess = 'closeModal()';
    } else {
        $targetColumnId = $isEdit ? $task->column_id : ($columnId ?? null);
        $htmxTarget = $targetColumnId ? "#board-column-{$targetColumnId}-tasks" : '#taskModalContent';
        $htmxSwap = 'outerHTML';
        $htmxSuccess = '';
    }
    
    // Determine current phase from request or task
    $currentPhaseId = old('phase_id', $isEdit ? $task->phase_id : request()->query('phase'));
    
    // Default to no phase for new tasks (removed automatic phase selection)
    // Previously: would default to first active phase or first available phase
    // Now: defaults to null (no phase) unless explicitly specified
    
    // Get current phase for date display
    $currentPhase = $phases->firstWhere('id', $currentPhaseId);
    
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
    
    {{-- Preserve filters for redirect --}}
    @if(request('phase'))
        <input type="hidden" name="filter_phase_id" value="{{ request('phase') }}">
    @endif
    @if(request('assigned'))
        <input type="hidden" name="filter_assigned" value="{{ request('assigned') }}">
    @endif
    
    {{-- Project display for global modal (both create and edit) --}}
    @if($isGlobalModal)
    <div class="border-bottom bg-light px-3 py-2" style="flex-shrink: 0;">
        <small class="text-muted">Project: <strong>{{ $project->name }}</strong></small>
    </div>
    @endif
    
    {{-- Actions dropdown (for edit mode only) --}}
    @if($isEdit)
    <div class="border-bottom bg-light px-3 py-2" style="flex-shrink: 0;">
        <div class="d-flex justify-content-end">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="taskActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="taskActionsDropdown">
                    <li>
                        <a class="dropdown-item" href="{{ $task->phase ? route('projects.phases.tasks.show', [$project, $task->phase, $task]) : route('projects.board.tasks.show', [$project, $task]) }}">
                            View Details
                        </a>
                    </li>
                    @if(!Auth::user()->isClient())
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <button type="button" 
                                class="dropdown-item text-danger" 
                                onclick="if(confirm('Are you sure you want to delete this task? This action cannot be undone.')) { 
                                    htmx.ajax('DELETE', '{{ route('projects.board.tasks.destroy', [$project, $task]) }}', {
                                        target: '#board-column-{{ $task->column_id }}-tasks',
                                        swap: 'outerHTML'
                                    });
                                }">
                            <i class="bi bi-trash"></i> Delete Task
                        </button>
                    </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
    @endif
    
    {{-- Tabs - sticky at top --}}
    @if($isEdit)
    <div class="border-bottom px-3 task-form-tabs-bg overflow-x-auto" style="position: sticky; top: 0; z-index: 10; flex-shrink: 0;">
        <ul class="nav nav-tabs flex-nowrap" id="taskModalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ ($activeTab ?? 'details') === 'details' ? 'active' : '' }}" 
                        id="details-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#details" 
                        type="button" 
                        role="tab" 
                        aria-controls="details" 
                        aria-selected="{{ ($activeTab ?? 'details') === 'details' ? 'true' : 'false' }}">
                    Details
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ ($activeTab ?? 'details') === 'description' ? 'active' : '' }}" 
                        id="description-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#description-tab-content" 
                        type="button" 
                        role="tab" 
                        aria-controls="description-tab-content" 
                        aria-selected="{{ ($activeTab ?? 'details') === 'description' ? 'true' : 'false' }}">
                    Description
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ ($activeTab ?? 'details') === 'discussion' ? 'active' : '' }}" 
                        id="discussion-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#discussion" 
                        type="button" 
                        role="tab" 
                        aria-controls="discussion" 
                        aria-selected="{{ ($activeTab ?? 'details') === 'discussion' ? 'true' : 'false' }}">
                    Discussion
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link {{ ($activeTab ?? 'details') === 'attachments' ? 'active' : '' }}" 
                        id="attachments-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#attachments" 
                        type="button" 
                        role="tab" 
                        aria-controls="attachments" 
                        aria-selected="{{ ($activeTab ?? 'details') === 'attachments' ? 'true' : 'false' }}">
                    Attachments
                </button>
            </li>
        </ul>
    </div>
    @endif
    
    {{-- Scrollable body --}}
    <div style="flex: 1; min-height: 0; overflow-y: auto;">
        @if($isEdit)
        <div class="tab-content" id="taskModalTabContent" style="height: 100%;">
            {{-- Details Tab --}}
            <div class="tab-pane fade {{ ($activeTab ?? 'details') === 'details' ? 'show active' : '' }}" 
                 id="details" 
                 role="tabpanel" 
                 aria-labelledby="details-tab"
                 style="padding: 1rem 1.5rem; height: 100%; overflow-y: auto;">
        @else
        <div style="padding: 1rem 1.5rem; height: 100%; overflow-y: auto;">
        @endif
        
        {{-- Phase selector --}}
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

        {{-- Status and Column in columnar layout --}}
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="status" class="form-label">Status</label>
                <select 
                    class="form-select @error('status') is-invalid @enderror" 
                    id="status" 
                    name="status" 
                    required>
                    <option value="planned" {{ old('status', $isEdit ? $task->status : $defaultStatus) === 'planned' ? 'selected' : '' }}>Planned</option>
                    <option value="active" {{ old('status', $isEdit ? $task->status : $defaultStatus) === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="awaiting_feedback" {{ old('status', $isEdit ? $task->status : $defaultStatus) === 'awaiting_feedback' ? 'selected' : '' }}>Awaiting Feedback</option>
                    <option value="completed" {{ old('status', $isEdit ? $task->status : $defaultStatus) === 'completed' ? 'selected' : '' }}>Completed</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            @if($isEdit)
                <div class="col-md-6">
                    <label for="column_id_select" class="form-label">Column</label>
                    <select 
                        class="form-select @error('column_id') is-invalid @enderror" 
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
        </div>

        {{-- Dates in columnar layout --}}
        <div class="row mb-3">
            @if(!Auth::user()->isClient())
            <div class="col-md-6">
                <label for="start_date" class="form-label">Start Date</label>
                <input 
                    type="date" 
                    class="form-control @error('start_date') is-invalid @enderror" 
                    id="start_date" 
                    name="start_date" 
                    value="{{ old('start_date', $isEdit && $task->start_date ? $task->start_date->format('Y-m-d') : '') }}">
                @error('start_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @endif

            <div class="col-md-6">
                <label for="due_date" class="form-label">Due Date</label>
                <input 
                    type="date" 
                    class="form-control @error('due_date') is-invalid @enderror" 
                    id="due_date" 
                    name="due_date" 
                    value="{{ old('due_date', $isEdit && $task->due_date ? $task->due_date->format('Y-m-d') : '') }}">
                @error('due_date')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
        
        @if(!$isEdit || (!$task->start_date && !$task->due_date))
            <div class="text-muted small mb-3" style="margin-top: -0.75rem;">
                If no dates are set, this task will inherit the phase's date range.
            </div>
        @endif

        {{-- Assignment (hidden for clients) --}}
        @if(!Auth::user()->isClient())
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
        @endif

        {{-- Description editor (only for create mode, in edit mode it's in its own tab) --}}
        @if(!$isEdit)
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <input 
                id="description" 
                type="hidden" 
                name="description" 
                value="{{ old('description', '') }}">
            <trix-editor 
                input="description" 
                class="@error('description') is-invalid @enderror"
                style="min-height: 120px;"></trix-editor>
            @error('description')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>
        @endif

        {{-- Task Metadata (only for edit mode) --}}
        @if($isEdit)
            <div class="card bg-light border-0 mb-3">
                <div class="card-body p-3">
                    <h6 class="text-muted mb-2" style="font-size: 0.875rem;">Task Metadata</h6>
                    <div class="row g-2">
                        @if($task->creator)
                            <div class="col-md-6">
                                <small class="text-muted d-block">Created By</small>
                                <small>{{ $task->creator->name }}</small>
                            </div>
                        @endif
                        @if($task->updater)
                            <div class="col-md-6">
                                <small class="text-muted d-block">Last Updated By</small>
                                <small>{{ $task->updater->name }}</small>
                            </div>
                        @endif
                        @if($task->completed_at)
                            <div class="col-md-6">
                                <small class="text-muted d-block">Completed</small>
                                <small>{{ $task->completed_at->format('M j, Y g:i A') }}</small>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($isEdit)
            </div>
            
            {{-- Description Tab --}}
            <div class="tab-pane fade {{ ($activeTab ?? 'details') === 'description' ? 'show active' : '' }}" 
                 id="description-tab-content" 
                 role="tabpanel" 
                 aria-labelledby="description-tab"
                 style="padding: 1rem 1.5rem; height: 100%; overflow-y: auto;">
                
                {{-- Description editor --}}
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <input 
                        id="description" 
                        type="hidden" 
                        name="description" 
                        value="{{ old('description', $task->description) }}">
                    <trix-editor 
                        input="description" 
                        class="@error('description') is-invalid @enderror"
                        style="min-height: 120px;"></trix-editor>
                    @error('description')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            
            {{-- Discussion Tab --}}
            <div class="tab-pane fade {{ ($activeTab ?? 'details') === 'discussion' ? 'show active' : '' }}" 
                 id="discussion" 
                 role="tabpanel" 
                 aria-labelledby="discussion-tab"
                 style="height: 100%; overflow-y: auto;">
                
                {{-- Comment form - sticky at top --}}
                <div class="border-bottom task-form-tabs-bg" style="position: sticky; top: 0; z-index: 10; padding: 1rem 1.5rem; padding-bottom: 1rem;">
                    <div id="commentFormContainer">
                        <div class="mb-2">
                            <textarea 
                                id="commentBody"
                                name="body" 
                                class="form-control" 
                                rows="1" 
                                placeholder="Add a comment... (Ctrl+Enter to submit)"
                                style="resize: none; min-height: 36px;"></textarea>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">Press Ctrl+Enter to submit</small>
                            <button type="button" 
                                    id="postCommentBtn"
                                    class="btn btn-sm btn-outline-primary"
                                    data-comment-url="{{ route('projects.board.tasks.comments.store', [$project, $task]) }}"
                                    >Post comment</button>
                        </div>
                    </div>
                </div>
                
                {{-- Comments list - scrollable --}}
                <div style="padding: 1rem 1.5rem;">
                    @forelse($task->comments as $comment)
                        <div class="mb-3">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div class="d-flex align-items-start gap-2">
                                    <strong class="text-sm">{{ $comment->user->name }}</strong>
                                    <small class="text-muted">{{ $comment->created_at->diffForHumans() }}</small>
                                </div>
                                @php
                                    $canDelete = auth()->id() === $comment->user_id || (auth()->user() && auth()->user()->isAdmin());
                                @endphp
                                @if($canDelete)
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger"
                                            hx-delete="{{ route('projects.board.tasks.comments.destroy', [$project, $task, $comment]) }}"
                                            hx-target="#taskModalContent"
                                            hx-swap="innerHTML"
                                            hx-confirm="Are you sure you want to delete this comment?"
                                            title="Delete comment">
                                        x
                                    </button>
                                @endif
                            </div>
                            <div class="text-sm mt-1">{!! nl2br(e($comment->body)) !!}</div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-3">
                            <small>No comments yet. Start the discussion!</small>
                        </div>
                    @endforelse

                    {{-- Activity Log Section --}}
                    @if($task->activities->isNotEmpty())
                        <hr class="my-4">
                        <h6 class="text-muted mb-3">Activity</h6>
                        @foreach($task->activities as $activity)
                            <div class="mb-2 pb-2 border-bottom border-light">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <small class="text-muted d-block">
                                            {{ $activity->getSummary() }}
                                        </small>
                                    </div>
                                    <small class="text-muted text-nowrap">{{ $activity->created_at->diffForHumans() }}</small>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
            
            {{-- Attachments Tab --}}
            <div class="tab-pane fade {{ ($activeTab ?? 'details') === 'attachments' ? 'show active' : '' }}" 
                 id="attachments" 
                 role="tabpanel" 
                 aria-labelledby="attachments-tab"
                 style="height: 100%; overflow-y: auto;">
                
                {{-- Upload form - sticky at top --}}
                <div class="border-bottom task-form-tabs-bg" style="position: sticky; top: 0; z-index: 10; padding: 1rem 1.5rem; padding-bottom: 1rem;">
                    <div id="attachmentUploadStatus" class="alert alert-success alert-dismissible fade mb-2" role="alert" style="display: none;">
                        <span id="attachmentUploadMessage"></span>
                        <button type="button" class="btn-close" onclick="document.getElementById('attachmentUploadStatus').style.display='none'"></button>
                    </div>
                    <div class="d-flex gap-2 align-items-end">
                        <div style="flex: 1;">
                            <label for="attachment_file" class="form-label mb-1">Upload File</label>
                            <input type="file" 
                                   class="form-control form-control-sm" 
                                   id="attachment_file" 
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.txt,.csv,.xlsx,.xls"
                                   data-upload-url="{{ route('projects.board.tasks.attachments.upload', [$project, $task]) }}">
                            <small class="text-muted">Max 10MB. Images, PDFs, documents, and spreadsheets.</small>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" onclick="uploadAttachment()">Upload</button>
                    </div>
                </div>
                </div>
                
                {{-- Attachments list - scrollable --}}
                <div style="padding: 1rem 1.5rem;" id="attachmentsList">
                    @forelse($task->attachments as $attachment)
                        <div class="mb-3 pb-3 border-bottom" data-attachment-id="{{ $attachment->id }}">
                            <div class="d-flex align-items-start justify-content-between gap-2">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <a href="{{ route('projects.board.tasks.attachments.download', [$project, $task, $attachment]) }}" 
                                           class="text-decoration-none">
                                            <strong>{{ $attachment->original_name }}</strong>
                                        </a>
                                        <span class="badge bg-light text-muted border" style="font-weight: 400;">
                                            {{ number_format($attachment->size / 1024, 1) }} KB
                                        </span>
                                    </div>
                                    <small class="text-muted">
                                        Uploaded by {{ $attachment->user->name }} • {{ $attachment->created_at->diffForHumans() }}
                                    </small>
                                </div>
                                @if($attachment->user_id === auth()->id() || auth()->user()->isAdmin())
                                    <button type="button" 
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="deleteAttachment('{{ route('projects.board.tasks.attachments.destroy', [$project, $task, $attachment]) }}', {{ $attachment->id }})"
                                            title="Delete attachment">
                                        Delete
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-muted text-center py-3" id="noAttachmentsMessage">
                            <small>No attachments yet. Upload a file to get started.</small>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
        @else
        </div>
        @endif
    </div>
    
    {{-- Sticky footer action bar --}}
    <div class="modal-footer task-form-footer-bg" style="position: sticky; bottom: 0; padding: 0.75rem 1rem; flex-shrink: 0; z-index: 10;">
        <div class="d-flex justify-content-between align-items-center w-100">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">
                {{ $isEdit ? 'Save Task' : 'Create Task' }}
            </button>
        </div>
    </div>
</form>

<script>
// Show status message for attachments
function showAttachmentStatus(message, isError = false) {
    const statusDiv = document.getElementById('attachmentUploadStatus');
    const messageSpan = document.getElementById('attachmentUploadMessage');
    
    statusDiv.className = 'alert alert-dismissible fade show mb-2';
    statusDiv.className += isError ? ' alert-danger' : ' alert-success';
    messageSpan.textContent = message;
    statusDiv.style.display = 'block';
    
    // Auto-hide success messages after 3 seconds
    if (!isError) {
        setTimeout(() => {
            statusDiv.style.display = 'none';
        }, 3000);
    }
}

// Handle attachment upload without closing modal
function uploadAttachment() {
    const fileInput = document.getElementById('attachment_file');
    const file = fileInput.files[0];
    
    if (!file) {
        showAttachmentStatus('Please select a file to upload.', true);
        return;
    }
    
    // Check file size (10MB max)
    if (file.size > 10 * 1024 * 1024) {
        showAttachmentStatus('File size must be less than 10MB.', true);
        return;
    }
    
    const formData = new FormData();
    formData.append('file', file);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    
    const uploadUrl = fileInput.getAttribute('data-upload-url');
    const uploadBtn = event.target;
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch(uploadUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Upload failed');
            });
        }
        return response.json();
    })
    .then(data => {
        showAttachmentStatus('File uploaded successfully!');
        fileInput.value = ''; // Clear the file input
        
        // Add the new attachment to the list
        const attachmentsList = document.getElementById('attachmentsList');
        const noMessage = document.getElementById('noAttachmentsMessage');
        if (noMessage) {
            noMessage.remove();
        }
        
        // Insert new attachment at the top
        const newItem = document.createElement('div');
        newItem.className = 'mb-3 pb-3 border-bottom';
        newItem.setAttribute('data-attachment-id', data.id);
        newItem.innerHTML = `
            <div class="d-flex align-items-start justify-content-between gap-2">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <a href="${data.download_url}" class="text-decoration-none">
                            <strong>${data.original_name}</strong>
                        </a>
                        <span class="badge bg-light text-muted border" style="font-weight: 400;">
                            ${(data.size / 1024).toFixed(1)} KB
                        </span>
                    </div>
                    <small class="text-muted">
                        Uploaded by ${data.user_name} • just now
                    </small>
                </div>
                <button type="button" 
                        class="btn btn-sm btn-outline-danger"
                        onclick="deleteAttachment('${data.delete_url}', ${data.id})"
                        title="Delete attachment">
                    Delete
                </button>
            </div>
        `;
        attachmentsList.insertBefore(newItem, attachmentsList.firstChild);
    })
    .catch(error => {
        console.error('Upload error:', error);
        showAttachmentStatus(error.message || 'Failed to upload file.', true);
    })
    .finally(() => {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload';
    });
}

// Handle attachment delete without closing modal
function deleteAttachment(deleteUrl, attachmentId) {
    if (!confirm('Are you sure you want to delete this attachment?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);
    formData.append('_method', 'DELETE');
    
    fetch(deleteUrl, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(data => {
                throw new Error(data.message || 'Delete failed');
            });
        }
        return response.json();
    })
    .then(data => {
        showAttachmentStatus('Attachment deleted successfully!');
        
        // Remove the attachment from the list
        const attachmentItem = document.querySelector(`[data-attachment-id="${attachmentId}"]`);
        if (attachmentItem) {
            attachmentItem.remove();
        }
        
        // Show "no attachments" message if list is empty
        const attachmentsList = document.getElementById('attachmentsList');
        if (attachmentsList.children.length === 0) {
            attachmentsList.innerHTML = '<div class="text-muted text-center py-3" id="noAttachmentsMessage"><small>No attachments yet. Upload a file to get started.</small></div>';
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        showAttachmentStatus(error.message || 'Failed to delete attachment.', true);
    });
}

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

// Comment form handling
(function() {
    const commentBtn = document.getElementById('postCommentBtn');
    const textarea = document.getElementById('commentBody');
    
    if (!commentBtn || !textarea) return;
    
    // Expand textarea on focus
    textarea.addEventListener('focus', function() {
        this.rows = 3;
    });
    
    // Auto-resize textarea
    textarea.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });
    
    // Keyboard shortcuts
    textarea.addEventListener('keydown', function(e) {
        // Cmd/Ctrl + Enter to submit
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            e.stopPropagation();
            if (textarea.value.trim()) {
                commentBtn.click();
            }
        }
        // Esc to blur (don't close modal)
        else if (e.key === 'Escape') {
            e.preventDefault();
            this.blur();
        }
    });
    
    // Handle comment submission manually
    commentBtn.addEventListener('click', function(e) {
        e.preventDefault();
        const body = textarea.value.trim();
        if (!body) return;
        
        const url = commentBtn.getAttribute('data-comment-url');
        
        htmx.ajax('POST', url, {
            target: '#taskModalContent',
            swap: 'innerHTML',
            values: { body: body }
        });
    });
})();

// Trix inline attachment handling
(function() {
    const trixEditor = document.querySelector('trix-editor[input="description"]');
    if (!trixEditor) {
        console.error('Trix editor not found');
        return;
    }
    
    @if(isset($task))
    const uploadUrl = '{{ route("projects.board.tasks.attachments.trix", [$project, $task]) }}';
    const mode = 'edit';
    @else
    const uploadUrl = '{{ route("projects.board.tasks.attachments.trix-temp", $project) }}';
    const mode = 'create';
    @endif
    
    console.log('Trix attachment handler initialized for ' + mode + ' mode. Upload URL:', uploadUrl);
    
    // Handle attachment addition
    trixEditor.addEventListener('trix-attachment-add', function(event) {
        const attachment = event.attachment;
        if (!attachment.file) return;
        
        console.log('Uploading file to:', uploadUrl);
        
        // Upload the file
        const formData = new FormData();
        formData.append('file', attachment.file);
        
        fetch(uploadUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Upload failed: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.url && data.href) {
                attachment.setAttributes({
                    url: data.url,
                    href: data.href
                });
            } else {
                throw new Error('Invalid response from server');
            }
        })
        .catch(error => {
            console.error('Trix upload error:', error);
            attachment.remove();
            alert('Failed to upload file: ' + error.message);
        });
    });
})();
</script>

@if($isGlobalModal)
<script>
    // Update modal title for global modal
    document.addEventListener('DOMContentLoaded', function() {
        const modalTitle = document.getElementById('taskCreationModalLabel');
        if (modalTitle) {
            @if(isset($task))
            modalTitle.textContent = 'Edit Task in {{ addslashes($project->name) }}';
            @else
            modalTitle.textContent = 'Create Task in {{ addslashes($project->name) }}';
            @endif
        }
    });
</script>
@endif
