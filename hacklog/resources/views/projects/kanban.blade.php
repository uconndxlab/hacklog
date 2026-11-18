@extends('layouts.app')

@section('title', 'Project Board')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">Project Board</h1>
        <p class="text-muted mb-0">All projects grouped by their current status.</p>
    </div>
    <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">List view</a>
</div>

<div class="board-container" id="project-kanban" aria-label="Projects by status">
    @foreach($statuses as $status)
        @php($cards = $projectsByStatus->get($status->key, collect()))
        <div class="board-column-wrapper">
            <section class="card h-100 board-column" data-status="{{ $status->key }}" aria-label="{{ $status->name }} projects">
                <header class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">{{ $status->name }}</h2>
                    <small class="text-muted project-kanban-count">{{ $cards->count() }} {{ Str::plural('project', $cards->count()) }}</small>
                </header>
                <div class="card-body p-2 project-kanban-cards" @if($canMoveProjects) data-drop-status="{{ $status->key }}" @endif>
                    @forelse($cards as $project)
                        <article class="card mb-2 shadow-sm project-kanban-card" data-project-id="{{ $project->id }}" @if($canMoveProjects) draggable="true" @endif>
                            <div class="card-body p-3">
                                <h3 class="h6 card-title mb-2">
                                    <a class="text-decoration-none fw-semibold" href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                                </h3>
                                <div class="small text-muted">
                                    @if($project->department)
                                        <div>{{ $project->department->name }}@if($project->nestedDepartment) / {{ $project->nestedDepartment->name }}@endif</div>
                                    @endif
                                    @if($project->projectTypeLabel())<div>{{ $project->projectTypeLabel() }}</div>@endif
                                    @if($project->launch_date)<div>Launch {{ $project->launch_date->format('M j, Y') }}</div>@endif
                                </div>
                                @if($project->tags->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-1 mt-2">
                                        @foreach($project->tags as $tag)
                                            <span class="badge rounded-pill" style="background-color: {{ $tag->color ?? '#6c757d' }}">{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="text-muted text-center py-4 project-kanban-empty"><small>No projects in this column</small></div>
                    @endforelse
                </div>
            </section>
        </div>
    @endforeach
</div>

@if($canMoveProjects)
<div id="project-kanban-feedback" class="visually-hidden" role="status" aria-live="polite"></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const board = document.getElementById('project-kanban');
    let dragged = null;
    board.querySelectorAll('.project-kanban-card[draggable="true"]').forEach(card => {
        card.addEventListener('dragstart', event => {
            dragged = card;
            card.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.projectId);
        });
        card.addEventListener('dragend', () => {
            card.classList.remove('dragging');
            dragged = null;
            board.querySelectorAll('.board-column.drop-target').forEach(column => column.classList.remove('drop-target'));
        });
    });

    board.querySelectorAll('[data-drop-status]').forEach(target => {
        target.addEventListener('dragover', event => {
            event.preventDefault();
            target.closest('.board-column').classList.add('drop-target');
        });
        target.addEventListener('dragleave', event => {
            if (!target.contains(event.relatedTarget)) target.closest('.board-column').classList.remove('drop-target');
        });
        target.addEventListener('drop', async event => {
            event.preventDefault();
            target.closest('.board-column').classList.remove('drop-target');
            if (!dragged || dragged.closest('[data-drop-status]') === target) return;

            const card = dragged;
            const previous = card.parentElement;
            const previousEmpty = previous.querySelector('.project-kanban-empty');
            const destinationEmpty = target.querySelector('.project-kanban-empty');
            card.remove();
            if (previousEmpty && previous.querySelectorAll('.project-kanban-card').length === 0) previousEmpty.hidden = false;
            if (destinationEmpty) destinationEmpty.hidden = true;
            target.appendChild(card);
            updateCounts();

            try {
                const response = await fetch(@json(url('/projects')) + '/' + card.dataset.projectId + '/status', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: target.dataset.dropStatus })
                });
                if (!response.ok) throw new Error('Could not update project status.');
                const result = await response.json();
                document.getElementById('project-kanban-feedback').textContent = 'Project moved to ' + result.status + '.';
            } catch (error) {
                card.remove();
                if (destinationEmpty) destinationEmpty.hidden = false;
                previous.appendChild(card);
                if (previousEmpty) previousEmpty.hidden = true;
                updateCounts();
                document.getElementById('project-kanban-feedback').textContent = error.message;
            }
        });
    });

    function updateCounts() {
        board.querySelectorAll('.board-column').forEach(column => {
            const count = column.querySelectorAll('.project-kanban-card').length;
            column.querySelector('.project-kanban-count').textContent = count + ' ' + (count === 1 ? 'project' : 'projects');
        });
    }
});
</script>
@endif

@endsection
