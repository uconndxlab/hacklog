@extends('layouts.app')

@section('title', 'AI Intake — ' . $project->name)

@section('content')
<div class="row">
    <div class="col-lg-10">

        @include('projects.partials.project-header')
        @include('projects.partials.project-nav', ['currentView' => 'intake'])

        {{-- Back link --}}
        <div class="mb-3">
            <a href="{{ route('projects.intake.index', $project) }}" class="text-muted small">
                ← All intakes
            </a>
        </div>

        {{-- Status banner --}}
        @php
            $statusBannerClass = match($intake->status) {
                'ready'      => 'alert-success',
                'processing' => 'alert-info',
                'queued'     => 'alert-info',
                'failed'     => 'alert-danger',
                default      => 'alert-secondary',
            };
        @endphp
        <div class="alert {{ $statusBannerClass }} py-2 d-flex align-items-center gap-2 mb-3" id="status-banner" role="status">
            @if($intake->status === 'processing')
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                <span><strong>Analyzing…</strong> Hacklog AI is reviewing your notes.</span>
            @elseif($intake->status === 'queued')
                <span><strong>Queued.</strong> Analysis will begin shortly.</span>
            @elseif($intake->status === 'ready')
                <span>
                    <strong>Ready.</strong>
                    {{ $intake->proposals->where('status', 'pending')->count() }} proposal(s) to review.
                    @if($intake->processing_completed_at)
                        Completed {{ $intake->processing_completed_at->diffForHumans() }}.
                    @endif
                </span>
            @elseif($intake->status === 'failed')
                <span>
                    <strong>Analysis failed.</strong>
                    {{ $intake->error_message ?? 'An unknown error occurred.' }}
                </span>
            @endif
        </div>

        {{-- Source text disclosure --}}
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between py-2">
                <span class="small text-muted">
                    Source text
                    @if($intake->user)
                        &middot; submitted by {{ $intake->user->name }}
                        &middot; {{ $intake->created_at->format('M j, Y g:i a') }}
                    @endif
                </span>
                <button
                    class="btn btn-sm btn-outline-secondary"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#source-area"
                    aria-expanded="false"
                    aria-controls="source-area">
                    View source
                </button>
            </div>
            <div class="collapse" id="source-area">
                <div class="card-body">
                    <pre class="mb-0 small text-muted" style="white-space: pre-wrap; word-break: break-word;">{{ $intake->source_content }}</pre>
                </div>
            </div>
        </div>

        {{-- Proposals — only shown when ready --}}
        @if($intake->status === 'ready')
            @php
                $pendingProposals   = $intake->proposals->where('status', 'pending')->values();
                $approvedProposals  = $intake->proposals->where('status', 'approved')->values();
                $dismissedProposals = $intake->proposals->where('status', 'dismissed')->values();
            @endphp

            {{-- Pending proposals --}}
            @if($pendingProposals->isNotEmpty())
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-semibold">
                        Proposed Tasks
                        <span class="badge bg-secondary ms-1" id="proposals-count">{{ $pendingProposals->count() }}</span>
                    </span>
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
                </div>

                <ul class="list-group mb-4" id="proposals-list">
                    @foreach($pendingProposals as $proposal)
                        @php
                            $phaseName    = $proposal->suggestedPhase?->name;
                            $assigneeName = $proposal->suggestedAssignee?->name;
                            $dueDateDisplay = $proposal->due_date
                                ? $proposal->due_date->format('M j')
                                : null;
                            $conf      = $proposal->confidence;
                            $confClass = $conf === null ? 'bg-secondary'
                                : ($conf >= 0.8 ? 'bg-success' : ($conf >= 0.5 ? 'bg-warning text-dark' : 'bg-secondary'));
                        @endphp

                        <li class="list-group-item px-3 py-2 proposal-card"
                            id="proposal-{{ $proposal->id }}">

                            <div class="d-flex align-items-start gap-2">

                                {{-- Checkbox --}}
                                <div class="pt-1 flex-shrink-0">
                                    <input
                                        type="checkbox"
                                        class="form-check-input proposal-check"
                                        data-proposal-id="{{ $proposal->id }}"
                                        onchange="updateApproveButton()">
                                </div>

                                {{-- Content --}}
                                <div class="flex-grow-1" style="min-width: 0;">

                                    {{-- Title + badges row --}}
                                    <div class="d-flex align-items-start justify-content-between gap-2">
                                        <div style="min-width: 0;">
                                            <span class="fw-semibold">{{ $proposal->title }}</span>
                                            @if(!empty($proposal->possible_duplicate_of))
                                                <span
                                                    class="badge bg-warning text-dark ms-1"
                                                    style="font-size: 0.68rem; vertical-align: middle;"
                                                    title="May be similar to: {{ $proposal->possible_duplicate_of }}">⚠ dup</span>
                                            @endif
                                        </div>
                                        {{-- Confidence + actions --}}
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
                                                data-bs-target="#edit-panel-{{ $proposal->id }}"
                                                aria-expanded="false">Edit</button>

                                            {{-- Dismiss split button --}}
                                            <div class="btn-group" role="group">
                                                <form
                                                    id="dismiss-form-{{ $proposal->id }}"
                                                    action="{{ route('projects.intake.proposals.dismiss', [$project, $intake, $proposal]) }}"
                                                    method="POST"
                                                    class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="reason" id="dismiss-reason-{{ $proposal->id }}" value="">
                                                    <button type="submit" class="btn btn-link btn-sm p-0 text-muted" style="font-size: 0.82rem;">Dismiss</button>
                                                </form>
                                                <button
                                                    type="button"
                                                    class="btn btn-link btn-sm p-0 text-muted dropdown-toggle dropdown-toggle-split"
                                                    style="font-size: 0.82rem; padding-left: 2px !important;"
                                                    data-bs-toggle="dropdown"
                                                    aria-label="Dismiss with reason">
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end" style="font-size: 0.82rem; min-width: 10rem;">
                                                    @foreach(\App\Models\ProjectIntakeProposal::DISMISSAL_REASONS as $value => $label)
                                                        <li>
                                                            <button
                                                                type="button"
                                                                class="dropdown-item"
                                                                onclick="submitDismiss({{ $proposal->id }}, '{{ $value }}')">
                                                                {{ $label }}
                                                            </button>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Metadata --}}
                                    <div class="text-muted mt-1" style="font-size: 0.8rem;">
                                        {{ $phaseName ?? '—' }} &middot;
                                        {{ $assigneeName ?? 'Unassigned' }}
                                        @if($dueDateDisplay) &middot; {{ $dueDateDisplay }} @endif
                                    </div>

                                    {{-- Optional disclosures --}}
                                    @if(!empty($proposal->description))
                                        <details class="mt-1" style="font-size: 0.8rem;">
                                            <summary class="text-muted" style="cursor: pointer;">Description</summary>
                                            <div class="mt-1 ps-2 text-muted">{{ $proposal->description }}</div>
                                        </details>
                                    @endif

                                    @if(!empty($proposal->source_excerpt))
                                        <details class="mt-1" style="font-size: 0.8rem;">
                                            <summary class="text-muted" style="cursor: pointer;">Source</summary>
                                            <div class="mt-1 ps-2 fst-italic text-muted">&ldquo;{{ $proposal->source_excerpt }}&rdquo;</div>
                                        </details>
                                    @endif

                                    @if(!empty($proposal->possible_duplicate_of))
                                        <details class="mt-1" style="font-size: 0.8rem;">
                                            <summary class="text-warning" style="cursor: pointer;">&#9888; Possible duplicate</summary>
                                            <div class="mt-1 ps-2 text-muted">
                                                Similar to existing task:
                                                @if(isset($duplicateTasks[$proposal->possible_duplicate_of]))
                                                    @php $dupTask = $duplicateTasks[$proposal->possible_duplicate_of]; @endphp
                                                    <a href="{{ route('projects.board.tasks.edit', [$project, $dupTask]) }}"
                                                       data-bs-toggle="modal"
                                                       data-bs-target="#taskModal"
                                                       hx-get="{{ route('projects.board.tasks.edit', [$project, $dupTask]) }}"
                                                       hx-target="#taskModalContent"
                                                       class="fst-italic">
                                                        {{ $proposal->possible_duplicate_of }}
                                                    </a>
                                                @else
                                                    <em>{{ $proposal->possible_duplicate_of }}</em>
                                                @endif
                                            </div>
                                        </details>
                                    @endif

                                    {{-- Edit panel (Bootstrap collapse) --}}
                                    <div class="collapse mt-2" id="edit-panel-{{ $proposal->id }}">
                                        <div class="border rounded p-2 bg-light">
                                            <form
                                                action="{{ route('projects.intake.proposals.approve', [$project, $intake, $proposal]) }}"
                                                method="POST"
                                                id="proposal-form-{{ $proposal->id }}">
                                                @csrf
                                                <div class="row g-2">
                                                    <div class="col-12">
                                                        <input
                                                            type="text"
                                                            class="form-control form-control-sm"
                                                            name="title"
                                                            value="{{ $proposal->title }}"
                                                            placeholder="Title"
                                                            required>
                                                    </div>
                                                    <div class="col-12">
                                                        <textarea
                                                            class="form-control form-control-sm"
                                                            name="description"
                                                            rows="2"
                                                            placeholder="Description (optional)">{{ $proposal->description ?? '' }}</textarea>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <select class="form-select form-select-sm" name="phase_id">
                                                            <option value="">— No phase —</option>
                                                            @foreach($phases as $phase)
                                                                <option value="{{ $phase->id }}"
                                                                    @selected($proposal->suggested_phase_id == $phase->id)>
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
                                                                    @selected($defaultAssigneeId == $user->id)>
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
                                                            value="{{ $proposal->due_date?->format('Y-m-d') ?? '' }}">
                                                    </div>
                                                </div>
                                                <div class="mt-2 d-flex gap-2">
                                                    <button type="submit" class="btn btn-success btn-sm">Add to Project</button>
                                                    <button
                                                        type="button"
                                                        class="btn btn-outline-secondary btn-sm"
                                                        data-bs-toggle="collapse"
                                                        data-bs-target="#edit-panel-{{ $proposal->id }}">Cancel</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @elseif($intake->status === 'ready' && $intake->proposals->isEmpty())
                <div class="alert alert-info py-2 small mb-4">
                    No actionable tasks were identified in the submitted content.
                </div>
            @elseif($intake->status === 'ready')
                <div class="alert alert-success py-2 small mb-4">
                    All proposals have been reviewed.
                </div>
            @endif

            {{-- Approved proposals (read-only, collapsible) --}}
            @if($approvedProposals->isNotEmpty())
                <details class="mb-3">
                    <summary class="small fw-semibold text-muted" style="cursor: pointer;">
                        Approved ({{ $approvedProposals->count() }})
                    </summary>
                    <ul class="list-group mt-2">
                        @foreach($approvedProposals as $proposal)
                            <li class="list-group-item py-2 px-3 d-flex align-items-center justify-content-between">
                                <span class="small">{{ $proposal->title }}</span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-success" style="font-size: 0.68rem;">Approved</span>
                                    @if($proposal->createdTask)
                                        <a href="{{ route('projects.board', $project) }}"
                                           class="small text-muted">View task</a>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            {{-- Dismissed proposals (read-only, collapsible) --}}
            @if($dismissedProposals->isNotEmpty())
                <details class="mb-3">
                    <summary class="small fw-semibold text-muted" style="cursor: pointer;">
                        Dismissed ({{ $dismissedProposals->count() }})
                    </summary>
                    <ul class="list-group mt-2">
                        @foreach($dismissedProposals as $proposal)
                            <li class="list-group-item py-2 px-3 d-flex align-items-center justify-content-between">
                                <span class="small text-muted">{{ $proposal->title }}</span>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-secondary" style="font-size: 0.68rem;">Dismissed</span>
                                    @if($proposal->dispositionLabel())
                                        <span class="small text-muted">{{ $proposal->dispositionLabel() }}</span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

        @endif {{-- end status === ready --}}

    </div>
</div>

{{-- Task modal shell — same structure as board.blade.php, loaded via HTMX --}}
<div class="modal fade" id="taskModal" tabindex="-1" aria-labelledby="taskModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header py-2" style="flex-shrink: 0;">
                <h5 class="modal-title mb-0" id="taskModalLabel">Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" id="taskModalContent" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column;">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    {{-- Polling: only active while status is queued or processing --}}
    @if(!$intake->isTerminal())
    (function poll() {
        const url = {{ Js::from(route('projects.intake.status', [$project, $intake])) }};
        let attempts = 0;

        const interval = setInterval(async () => {
            attempts++;
            if (attempts > 120) { clearInterval(interval); return; } // stop after ~6 min

            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!res.ok) return;
                const data = await res.json();
                if (data.status === 'ready' || data.status === 'failed') {
                    clearInterval(interval);
                    window.location.reload();
                }
            } catch (_) {}
        }, 3000);
    }());
    @endif

    {{-- Proposal review helpers (only needed when there are pending proposals) --}}
    @if($intake->status === 'ready' && $pendingProposals->isNotEmpty())

    function updateApproveButton() {
        const any = document.querySelectorAll('.proposal-check:checked').length > 0;
        const btn = document.getElementById('approve-selected-btn');
        if (btn) btn.disabled = !any;
    }

    function selectAllProposals() {
        document.querySelectorAll('.proposal-check').forEach(cb => { cb.checked = true; });
        updateApproveButton();
    }

    function submitDismiss(proposalId, reason) {
        const reasonInput = document.getElementById('dismiss-reason-' + proposalId);
        if (reasonInput) reasonInput.value = reason;
        const form = document.getElementById('dismiss-form-' + proposalId);
        if (form) form.submit();
    }

    function approveSelected() {
        const checked = document.querySelectorAll('.proposal-check:checked');
        if (!checked.length) return;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = {{ Js::from(route('projects.intake.proposals.bulk-approve', [$project, $intake])) }};

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        form.appendChild(csrf);

        checked.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'proposal_ids[]';
            input.value = cb.dataset.proposalId;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    }

    // Expose to inline onclick handlers
    window.updateApproveButton = updateApproveButton;
    window.selectAllProposals  = selectAllProposals;
    window.submitDismiss       = submitDismiss;
    window.approveSelected     = approveSelected;

    @endif

}());
</script>
@endpush
