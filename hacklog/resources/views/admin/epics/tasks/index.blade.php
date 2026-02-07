@extends('layouts.app')

@section('title', 'Admin: ' . $epic->name . ' Tasks')

@section('content')
<div class="row">
    <div class="col-lg-12">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">Projects</a></li>
                <li class="breadcrumb-item"><a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a></li>
                <li class="breadcrumb-item"><a href="{{ route('projects.epics.show', [$project, $epic]) }}">{{ $epic->name }}</a></li>
                <li class="breadcrumb-item active">Admin: Tasks</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h1 class="mb-1">Admin: Task Cleanup</h1>
                <p class="text-muted mb-0">Epic: {{ $epic->name }}</p>
                <small class="text-muted">{{ $tasks->count() }} task(s) total</small>
            </div>
        </div>

        @if($tasks->isEmpty())
            <div class="alert alert-info">
                <h5 class="alert-heading">No tasks found</h5>
                <p class="mb-0">This epic doesn't have any tasks yet.</p>
            </div>
        @else
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Tasks in this Epic</h5>
                    <div class="d-flex gap-2">
                        <button type="button" id="selectAll" class="btn btn-sm btn-outline-secondary">Select All</button>
                        <button type="button" id="selectNone" class="btn btn-sm btn-outline-secondary">Select None</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form id="bulkDeleteForm" method="POST" action="{{ route('admin.epics.tasks.bulk-delete', [$project, $epic]) }}">
                        @csrf
                        @method('DELETE')
                        
                        <div class="list-group list-group-flush">
                            @foreach($tasks as $task)
                                <div class="list-group-item">
                                    <div class="d-flex align-items-start">
                                        <div class="form-check me-3 mt-1">
                                            <input class="form-check-input task-checkbox" type="checkbox" 
                                                   name="task_ids[]" value="{{ $task->id }}" id="task-{{ $task->id }}">
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-1">{{ $task->title }}</h6>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <span class="badge 
                                                        @if($task->status === 'planned') bg-secondary
                                                        @elseif($task->status === 'active') bg-success
                                                        @else bg-primary
                                                        @endif">
                                                        {{ ucfirst($task->status) }}
                                                    </span>
                                                    <small class="text-muted">{{ $task->column->name }}</small>
                                                </div>
                                            </div>
                                            
                                            @if($task->description)
                                                <p class="mb-2 text-muted small">{{ Str::limit($task->description, 100) }}</p>
                                            @endif
                                            
                                            <div class="d-flex gap-3 align-items-center text-muted small">
                                                @if($task->users->isNotEmpty())
                                                    <div>
                                                        <strong>Assigned:</strong> {{ $task->users->pluck('name')->join(', ') }}
                                                    </div>
                                                @endif
                                                
                                                @if($task->due_date)
                                                    <div>
                                                        <strong>Due:</strong> {{ $task->due_date->format('M j, Y') }}
                                                        @if($task->isOverdue())
                                                            <span class="badge bg-danger ms-1">Overdue</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                
                                                @if($task->start_date)
                                                    <div>
                                                        <strong>Start:</strong> {{ $task->start_date->format('M j, Y') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span id="selectedCount">0</span> task(s) selected
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">Cancel</button>
                                    <button type="submit" id="deleteButton" class="btn btn-danger" disabled
                                            onclick="return confirm('Are you sure you want to delete the selected tasks? This action cannot be undone.')">
                                        Delete Selected Tasks
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.task-checkbox');
    const selectAllBtn = document.getElementById('selectAll');
    const selectNoneBtn = document.getElementById('selectNone');
    const selectedCountSpan = document.getElementById('selectedCount');
    const deleteButton = document.getElementById('deleteButton');
    
    function updateSelectedCount() {
        const checkedCount = document.querySelectorAll('.task-checkbox:checked').length;
        selectedCountSpan.textContent = checkedCount;
        deleteButton.disabled = checkedCount === 0;
    }
    
    // Individual checkbox listeners
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCount);
    });
    
    // Select all button
    selectAllBtn.addEventListener('click', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectedCount();
    });
    
    // Select none button
    selectNoneBtn.addEventListener('click', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedCount();
    });
    
    // Initial count update
    updateSelectedCount();
});
</script>
@endsection