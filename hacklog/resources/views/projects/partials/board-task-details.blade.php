{{-- Task details for board modal --}}
<div class="mb-3">
    <h5 class="mb-2">{{ $task->title }}</h5>
    <span class="badge 
        @if($task->status === 'planned') bg-secondary
        @elseif($task->status === 'active') bg-success
        @else bg-primary
        @endif">
        {{ ucfirst($task->status) }}
    </span>
</div>

@if($task->description)
    <div class="card mb-3">
        <div class="card-header bg-light">
            <h6 class="mb-0 fw-semibold">Description</h6>
        </div>
        <div class="card-body">
            <div class="trix-content">
                {!! $task->description !!}
            </div>
        </div>
    </div>
@endif

<div class="card">
    <div class="card-header bg-light">
        <h6 class="mb-0 fw-semibold">Task Information</h6>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-4">Phase</dt>
            <dd class="col-sm-8">{{ $task->phase->name }}</dd>

            <dt class="col-sm-4">Column</dt>
            <dd class="col-sm-8">{{ $task->column->name }}</dd>

            @if($task->users->isNotEmpty())
                <dt class="col-sm-4">Assigned To</dt>
                <dd class="col-sm-8">{{ $task->users->pluck('name')->join(', ') }}</dd>
            @endif

            @if($task->start_date)
                <dt class="col-sm-4">Start Date</dt>
                <dd class="col-sm-8">{{ $task->start_date->format('F j, Y') }}</dd>
            @endif

            @if($task->due_date)
                <dt class="col-sm-4">Due Date</dt>
                <dd class="col-sm-8">{{ $task->due_date->format('F j, Y') }}</dd>
            @endif

            <dt class="col-sm-4">Created</dt>
            <dd class="col-sm-8">{{ $task->created_at->format('F j, Y \a\t g:i A') }}</dd>

            <dt class="col-sm-4">Last Updated</dt>
            <dd class="col-sm-8 mb-0">{{ $task->updated_at->format('F j, Y \a\t g:i A') }}</dd>
        </dl>
    </div>
</div>

<div class="mt-3 d-flex gap-2">
    <a href="{{ route('projects.phases.tasks.show', [$project, $task->phase, $task]) }}" class="btn btn-outline-secondary">
        View Full Page
    </a>
    <button 
        type="button" 
        class="btn btn-primary"
        data-bs-toggle="modal" 
        data-bs-target="#taskModal"
        data-bs-dismiss="modal"
        hx-get="{{ route('projects.board.tasks.edit', [$project, $task]) }}"
        hx-target="#taskModalContent">
        Edit Task
    </button>
    <button type="button" class="btn btn-secondary ms-auto" data-bs-dismiss="modal">Close</button>
</div>
