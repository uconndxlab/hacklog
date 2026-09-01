@extends('layouts.app')

@section('title', 'Reports — Workload')

@section('content')
@php
    $totalAssignments = $rows->sum('visible_count');
    $legendStatuses = collect(\App\Http\Controllers\ReportController::STATUS_LABELS)
        ->filter(fn ($label, $status) => in_array($status, $presentStatuses, true));
@endphp

@include('reports.partials.nav', [
    'title' => 'Staff Workload',
    'subtitle' => $rows->count().' staff member'.($rows->count() === 1 ? '' : 's').', '.$totalAssignments.' assignment'.($totalAssignments === 1 ? '' : 's').' from open work and completions in the last 20 days',
])

@if($legendStatuses->isNotEmpty())
    <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
        @foreach($legendStatuses as $status => $label)
            @php
                $isHidden = in_array($status, $hiddenStatuses, true);
                $newList = $isHidden
                    ? array_values(array_diff($hiddenStatuses, [$status]))
                    : array_values(array_merge($hiddenStatuses, [$status]));
                $toggleUrl = count($newList)
                    ? route('reports.workload', ['hide' => implode(',', $newList)])
                    : route('reports.workload').'?hide=';
            @endphp
            <a href="{{ $toggleUrl }}"
               class="badge rounded-pill text-decoration-none report-status-toggle {{ $isHidden ? 'opacity-50' : '' }}">
                <span class="report-status-swatch" style="background-color: {{ $statusColors[$status] ?? '#9ca3af' }};"></span>
                <span class="{{ $isHidden ? 'text-decoration-line-through' : '' }}">{{ $label }}</span>
            </a>
        @endforeach

        @if(count($hiddenStatuses) > 0)
            <a href="{{ route('reports.workload').'?hide=' }}" class="small text-muted">Show all</a>
        @endif
    </div>
@endif

@if($rows->isEmpty())
    <div class="card">
        <div class="card-body text-center text-muted py-5">
            No current task assignments found.
        </div>
    </div>
@else
    <div class="card">
        <div class="list-group list-group-flush">
            @foreach($rows as $row)
                @php $byStatus = $row->projects->groupBy('status'); @endphp
                <div class="list-group-item d-flex align-items-center gap-3 py-3">
                    <div class="report-workload-name">
                        <a href="{{ route('users.show', $row->user) }}" class="fw-semibold text-decoration-none">
                            {{ $row->user->name }}
                        </a>
                    </div>
                    <div class="flex-grow-1">
                        <div class="report-workload-track">
                            @foreach(\App\Models\Project::STATUS_VALUES as $status)
                                @php
                                    $group = $byStatus->get($status, collect());
                                    $count = $group->count();
                                    $pct = round($count / $maxCount * 100, 4);
                                    $names = $group->pluck('name')->join('|');
                                @endphp
                                @if($count > 0 && ! in_array($status, $hiddenStatuses, true))
                                    <div class="report-workload-segment"
                                         style="width: {{ $pct }}%; background-color: {{ $statusColors[$status] ?? '#9ca3af' }};"
                                         data-tooltip-status="{{ \App\Http\Controllers\ReportController::STATUS_LABELS[$status] ?? $status }}"
                                         data-tooltip-projects="{{ $names }}"></div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="report-workload-count fw-semibold">{{ $row->visible_count }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div id="workload-tooltip" class="report-workload-tooltip">
    <div id="tt-status" class="fw-semibold small mb-1"></div>
    <ul id="tt-projects" class="mb-0 ps-3 small"></ul>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const tooltip = document.getElementById('workload-tooltip');
    const statusEl = document.getElementById('tt-status');
    const projectsEl = document.getElementById('tt-projects');
    if (!tooltip) { return; }

    function escapeHtml(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function positionTooltip(e) {
        const pad = 8;
        tooltip.style.left = Math.min(e.clientX + 14, window.innerWidth - tooltip.offsetWidth - pad) + 'px';
        tooltip.style.top = Math.min(e.clientY + 14, window.innerHeight - tooltip.offsetHeight - pad) + 'px';
    }

    document.querySelectorAll('[data-tooltip-status]').forEach(function (seg) {
        seg.addEventListener('mouseenter', function (e) {
            statusEl.textContent = seg.dataset.tooltipStatus;
            const names = (seg.dataset.tooltipProjects || '').split('|').filter(Boolean);
            projectsEl.innerHTML = names.map(function (n) { return '<li>' + escapeHtml(n) + '</li>'; }).join('');
            tooltip.style.display = 'block';
            positionTooltip(e);
        });
        seg.addEventListener('mousemove', positionTooltip);
        seg.addEventListener('mouseleave', function () {
            tooltip.style.display = 'none';
        });
    });
}());
</script>
@endpush
