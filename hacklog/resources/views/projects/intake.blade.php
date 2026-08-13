@extends('layouts.app')

@section('title', 'AI Intake — ' . $project->name)

@section('content')
<div class="row">
    <div class="col-lg-10">

        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'intake'])

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- New intake submission form --}}
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">AI Intake</h2>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Paste meeting notes, an email, rough notes, or any project discussion below.
                    Hacklog AI will identify potential tasks for <strong>{{ $project->name }}</strong>.
                    Analysis runs in the background — nothing is created until you review and approve each proposal.
                </p>
                <form action="{{ route('projects.intake.store', $project) }}" method="POST">
                    @csrf
                    <div class="mb-2">
                        <textarea
                            class="form-control @error('input_text') is-invalid @enderror"
                            id="input_text"
                            name="input_text"
                            rows="10"
                            placeholder="Paste meeting notes, email, Slack conversation, or project-related text here..."
                            required>{{ old('input_text') }}</textarea>
                        @error('input_text')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Analyze</button>
                </form>
            </div>
        </div>

        {{-- Recent intakes for this project --}}
        @if($recentIntakes->isNotEmpty())
            <h2 class="h6 mb-2 text-muted">Recent Submissions</h2>
            <ul class="list-group mb-3">
                @foreach($recentIntakes as $intake)
                    @php
                        $statusClass = match($intake->status) {
                            'ready'      => 'bg-success',
                            'processing' => 'bg-info',
                            'queued'     => 'bg-secondary',
                            'failed'     => 'bg-danger',
                            default      => 'bg-secondary',
                        };
                        $statusLabel = match($intake->status) {
                            'ready'      => 'Ready',
                            'processing' => 'Analyzing…',
                            'queued'     => 'Queued',
                            'failed'     => 'Failed',
                            default      => ucfirst($intake->status),
                        };
                    @endphp
                    <a href="{{ route('projects.intake.show', [$project, $intake]) }}"
                       class="list-group-item list-group-item-action py-2">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div style="min-width: 0;">
                                <div class="small text-muted mb-1">
                                    {{ $intake->created_at->diffForHumans() }}
                                    @if($intake->user)
                                        &middot; {{ $intake->user->name }}
                                    @endif
                                </div>
                                <div class="text-truncate" style="font-size: 0.88rem;">
                                    {{ $intake->sourcePreview(100) }}
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="badge {{ $statusClass }}" style="font-size: 0.7rem;">{{ $statusLabel }}</span>
                                @if($intake->status === 'ready')
                                    <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                        @if($intake->pending_count > 0)
                                            {{ $intake->pending_count }} pending
                                        @endif
                                        @if($intake->approved_count > 0)
                                            &middot; {{ $intake->approved_count }} approved
                                        @endif
                                        @if($intake->dismissed_count > 0)
                                            &middot; {{ $intake->dismissed_count }} dismissed
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </ul>
        @endif

    </div>
</div>
@endsection

@section('content')
<div class="row">
    <div class="col-lg-10">

        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'intake'])

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Source input area --}}
        {{-- Expanded on fresh load; collapsed (with toggle) while proposals are shown --}}
        <div class="card mb-3">
            @isset($proposals)
                <div class="card-header d-flex align-items-center justify-content-between py-2">
                    <span class="small text-muted">Source text</span>
                    <button
                        class="btn btn-sm btn-outline-secondary"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#source-area"
                        aria-expanded="false"
                        aria-controls="source-area">
                        View / Re-analyze
                    </button>
                </div>
                <div class="collapse" id="source-area">
            @else
                <div class="card-header">
                    <h2 class="h5 mb-0">AI Intake</h2>
                </div>
                <div id="source-area">
            @endisset
                <div class="card-body">
                    @unless(isset($proposals))
                        <p class="text-muted small mb-3">
                            Paste meeting notes, an email, rough notes, or any project discussion below.
                            Hacklog AI will identify potential tasks for <strong>{{ $project->name }}</strong>.
                            Nothing is created until you approve each proposal.
                        </p>
                    @endunless
                    <form action="{{ route('projects.intake.store', $project) }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <textarea
                                class="form-control @error('input_text') is-invalid @enderror"
                                id="input_text"
                                name="input_text"
                                rows="{{ isset($proposals) ? 5 : 10 }}"
                                placeholder="Paste meeting notes, email, Slack conversation, or project-related text here..."
                                required>{{ old('input_text', $inputText ?? '') }}</textarea>
                            @error('input_text')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">Analyze</button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Proposals — only rendered after a successful analysis --}}
        @isset($proposals)

            {{-- Toolbar --}}
            <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-semibold">
                    Proposed Tasks
                    @if(!empty($proposals))
                        <span class="badge bg-secondary ms-1" id="proposals-count">{{ count($proposals) }}</span>
                    @endif
                </span>
                @if(!empty($proposals))
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="selectAllProposals()">
                            Select all
                        </button>
                        <button
                            type="button"
                            class="btn btn-sm btn-success"
                            id="approve-selected-btn"
                            onclick="approveSelected()"
                            disabled>
                            Approve selected
                        </button>
                    </div>
                @endif
            </div>

            @if(empty($proposals))
                <div class="alert alert-info py-2 small">
                    No actionable tasks were identified in the submitted content.
                </div>
            @else
                <ul class="list-group" id="proposals-list">
                    @foreach($proposals as $index => $proposal)
                        @php
                            $phaseName     = $phases->firstWhere('id', $proposal['suggested_phase_id'])?->name;
                            $assigneeName  = $users->firstWhere('id', $proposal['suggested_assignee_id'])?->name;
                            $dueDateDisplay = $proposal['due_date']
                                ? \Carbon\Carbon::parse($proposal['due_date'])->format('M j')
                                : null;
                            $conf      = $proposal['confidence'];
                            $confClass = $conf === null ? 'bg-secondary'
                                : ($conf >= 0.8 ? 'bg-success' : ($conf >= 0.5 ? 'bg-warning text-dark' : 'bg-secondary'));
                        @endphp

                        <li class="list-group-item px-3 py-2 proposal-card" id="proposal-{{ $index }}">

                            {{-- Compact summary row --}}
                            <div class="d-flex align-items-start gap-2">

                                {{-- Checkbox --}}
                                <div class="pt-1 flex-shrink-0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input proposal-check"
                                        id="check-{{ $index }}"
                                        data-proposal-index="{{ $index }}"
                                        onchange="updateApproveButton()">
                                </div>

                                {{-- Content --}}
                                <div class="flex-grow-1" style="min-width: 0;">

                                    {{-- Title + badges row --}}
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <div style="min-width: 0;">
                                            <span class="fw-semibold">{{ $proposal['title'] }}</span>
                                            @if(!empty($proposal['possible_duplicate_of']))
                                                <span
                                                    class="badge bg-warning text-dark ms-1"
                                                    style="font-size: 0.68rem; vertical-align: middle;"
                                                    title="May be similar to: {{ $proposal['possible_duplicate_of'] }}">⚠ dup</span>
                                            @endif
                                        </div>
                                        {{-- Actions --}}
                                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                            @if($conf !== null)
                                                <span
                                                    class="badge {{ $confClass }}"
                                                    style="font-size: 0.68rem;"
                                                    title="Model confidence: {{ round($conf * 100) }}%">{{ round($conf * 100) }}%</span>
                                            @endif
                                            <button
                                                type="button"
                                                class="btn btn-link btn-sm p-0"
                                                style="font-size: 0.82rem;"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#edit-panel-{{ $index }}"
                                                aria-expanded="false"
                                                aria-controls="edit-panel-{{ $index }}">Edit</button>
                                            <button
                                                type="button"
                                                class="btn btn-link btn-sm p-0 text-muted"
                                                style="font-size: 0.82rem;"
                                                onclick="dismissProposal({{ $index }})">Dismiss</button>
                                        </div>
                                    </div>

                                    {{-- Metadata: phase · assignee · due date --}}
                                    <div class="text-muted mt-1" style="font-size: 0.8rem;">
                                        {{ $phaseName ?? '—' }} &middot;
                                        {{ $assigneeName ?? 'Unassigned' }}
                                        @if($dueDateDisplay)
                                            &middot; {{ $dueDateDisplay }}
                                        @endif
                                    </div>

                                    {{-- Optional disclosures --}}
                                    @if(!empty($proposal['description']))
                                        <details class="mt-1" style="font-size: 0.8rem;">
                                            <summary class="text-muted" style="cursor: pointer;">Description</summary>
                                            <div class="mt-1 ps-2 text-muted">{{ $proposal['description'] }}</div>
                                        </details>
                                    @endif

                                    @if(!empty($proposal['source_excerpt']))
                                        <details class="mt-1" style="font-size: 0.8rem;">
                                            <summary class="text-muted" style="cursor: pointer;">Source</summary>
                                            <div class="mt-1 ps-2 fst-italic text-muted">&ldquo;{{ $proposal['source_excerpt'] }}&rdquo;</div>
                                        </details>
                                    @endif

                                    @if(!empty($proposal['possible_duplicate_of']))
                                        <details class="mt-1" style="font-size: 0.8rem;">
                                            <summary class="text-warning" style="cursor: pointer;">⚠ Possible duplicate</summary>
                                            <div class="mt-1 ps-2 text-muted">Similar to existing task: <em>{{ $proposal['possible_duplicate_of'] }}</em></div>
                                        </details>
                                    @endif

                                    {{-- Edit panel — Bootstrap collapse, expands inline --}}
                                    <div class="collapse mt-2" id="edit-panel-{{ $index }}">
                                        <div class="border rounded p-2 bg-light">
                                            <form
                                                id="proposal-form-{{ $index }}"
                                                action="{{ route('projects.intake.tasks.store', $project) }}"
                                                method="POST">
                                                @csrf
                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <input
                                                            type="text"
                                                            class="form-control form-control-sm"
                                                            name="title"
                                                            value="{{ $proposal['title'] }}"
                                                            placeholder="Title"
                                                            required>
                                                    </div>
                                                    <div class="col-12">
                                                        <textarea
                                                            class="form-control form-control-sm"
                                                            name="description"
                                                            rows="2"
                                                            placeholder="Description (optional)">{{ $proposal['description'] ?? '' }}</textarea>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <select class="form-select form-select-sm" name="phase_id">
                                                            <option value="">— No phase —</option>
                                                            @foreach($phases as $phase)
                                                                <option value="{{ $phase->id }}"
                                                                    @selected($proposal['suggested_phase_id'] == $phase->id)>
                                                                    {{ $phase->name }}@if($phase->status === 'active') (active)@endif
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <select class="form-select form-select-sm" name="assignee_id">
                                                            <option value="">— Unassigned —</option>
                                                            @foreach($users as $user)
                                                                <option value="{{ $user->id }}"
                                                                    @selected($proposal['suggested_assignee_id'] == $user->id)>
                                                                    {{ $user->name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <input
                                                            type="date"
                                                            class="form-control form-control-sm"
                                                            name="due_date"
                                                            value="{{ $proposal['due_date'] ?? '' }}">
                                                    </div>
                                                </div>
                                                <div class="mt-2 d-flex gap-2">
                                                    <button type="submit" class="btn btn-success btn-sm">Add to Project</button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary btn-sm"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#edit-panel-{{ $index }}">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

        @endisset

    </div>
</div>
@endsection

@isset($proposals)
@if(!empty($proposals))
@push('scripts')
<script>
(function () {
    'use strict';

    function updateApproveButton() {
        const any = document.querySelectorAll('.proposal-check:checked').length > 0;
        const btn = document.getElementById('approve-selected-btn');
        if (btn) btn.disabled = !any;
    }

    function selectAllProposals() {
        document.querySelectorAll('.proposal-check').forEach(cb => { cb.checked = true; });
        updateApproveButton();
    }

    function dismissProposal(idx) {
        const card = document.getElementById('proposal-' + idx);
        if (card) card.remove();
        const remaining = document.querySelectorAll('.proposal-card').length;
        const badge = document.getElementById('proposals-count');
        if (badge) badge.textContent = remaining;
        if (remaining === 0) {
            const list = document.getElementById('proposals-list');
            if (list) {
                list.insertAdjacentHTML('afterend',
                    '<p class="text-muted small mt-2">All proposals dismissed.</p>');
                list.remove();
            }
            const toolbar = document.getElementById('approve-selected-btn');
            if (toolbar) toolbar.closest('.d-flex')?.remove();
        }
        updateApproveButton();
    }

    function approveSelected() {
        const checked = document.querySelectorAll('.proposal-check:checked');
        if (!checked.length) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = {{ Js::from(route('projects.intake.bulk-tasks.store', $project)) }};

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        form.appendChild(csrf);

        checked.forEach((cb, i) => {
            const idx = cb.dataset.proposalIndex;
            const pf = document.getElementById('proposal-form-' + idx);
            if (!pf) return;
            ['title', 'description', 'phase_id', 'assignee_id', 'due_date'].forEach(field => {
                const el = pf.elements[field];
                if (!el) return;
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'tasks[' + i + '][' + field + ']';
                hidden.value = el.value;
                form.appendChild(hidden);
            });
        });

        document.body.appendChild(form);
        form.submit();
    }

    // Expose to inline onclick handlers
    window.updateApproveButton = updateApproveButton;
    window.selectAllProposals  = selectAllProposals;
    window.dismissProposal     = dismissProposal;
    window.approveSelected     = approveSelected;
}());
</script>
@endpush
@endif
@endisset

