@extends('layouts.app')

@section('title', 'Reports — Inventory')

@section('content')
@include('reports.partials.nav', [
    'title' => 'Project Inventory',
    'subtitle' => $projects->count().' project'.($projects->count() === 1 ? '' : 's').' found',
])

@include('reports.partials.charts')
@include('reports.partials.filters')

<div class="card pb-4 mb-5">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="report-inventory-table" data-sort="{{ $sort }}" data-direction="{{ $direction }}">
            <thead class="table-light">
                <tr>
                    @php
                        $sortLink = function (string $column, string $label, string $sortType, string $thClass = '') use ($sort, $direction) {
                            $nextDir = ($sort === $column && $direction === 'asc') ? 'desc' : 'asc';
                            $href = route('reports.index', array_filter(
                                array_merge(request()->except(['sort', 'direction', 'page']), [
                                    'sort' => $column,
                                    'direction' => $nextDir,
                                ]),
                                fn ($value) => $value !== null && $value !== ''
                            ));
                            $arrow = $sort === $column
                                ? ($direction === 'desc' ? ' ↓' : ' ↑')
                                : '';

                            return '<th class="'.$thClass.'"><a class="report-sort-link" href="'.e($href).'" data-column="'.e($column).'" data-sort-type="'.e($sortType).'"><span>'.e($label).'</span><span class="report-sort-arrow">'.$arrow.'</span></a></th>';
                        };
                    @endphp
                    {!! $sortLink('status', 'Status', 'number') !!}
                    {!! $sortLink('name', 'Project', 'text') !!}
                    {!! $sortLink('type', 'Type', 'number') !!}
                    {!! $sortLink('department', 'Department', 'text') !!}
                    {!! $sortLink('nested_department', 'Nested', 'text') !!}
                    {!! $sortLink('office', 'Office', 'text') !!}
                    {!! $sortLink('affiliation', 'Affiliation', 'number') !!}
                    {!! $sortLink('grant_value', 'Grant', 'number', 'text-end') !!}
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    @php
                        $statusOrder = array_search($project->status, \App\Models\Project::STATUS_VALUES, true);
                        $typeOrder = array_search($project->project_type, \App\Models\Project::TYPE_VALUES, true);
                        $affiliationOrder = array_search($project->uconn_affiliation, \App\Models\Project::AFFILIATION_VALUES, true);
                    @endphp
                    <tr data-sortable-row>
                        <td data-sort-col="status" data-sort="{{ $statusOrder === false ? 99 : $statusOrder }}">
                            <span class="badge
                                @if($project->status === 'planning') bg-info
                                @elseif($project->status === 'active') bg-success
                                @elseif($project->status === 'on_hold') bg-warning text-dark
                                @elseif($project->status === 'completed') bg-primary
                                @else bg-secondary
                                @endif">
                                {{ \App\Http\Controllers\ReportController::STATUS_LABELS[$project->status] ?? ucfirst(str_replace('_', ' ', $project->status)) }}
                            </span>
                        </td>
                        <td data-sort-col="name" data-sort="{{ $project->name }}">
                            <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-decoration-none">
                                {{ $project->name }}
                            </a>
                        </td>
                        <td data-sort-col="type" data-sort="{{ $typeOrder === false ? 99 : $typeOrder }}">
                            @if($project->projectTypeLabel())
                                <span class="small">{{ $project->projectTypeLabel() }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td data-sort-col="department" data-sort="{{ $project->department?->name }}">
                            @if($project->department)
                                <span class="small">{{ $project->department->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td data-sort-col="nested_department" data-sort="{{ $project->nestedDepartment?->name }}">
                            @if($project->nestedDepartment)
                                <span class="small">{{ $project->nestedDepartment->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td data-sort-col="office" data-sort="{{ $project->majorOffice?->name }}">
                            @if($project->majorOffice)
                                <span class="small">{{ $project->majorOffice->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td data-sort-col="affiliation" data-sort="{{ $affiliationOrder === false ? 99 : $affiliationOrder }}">
                            @if($project->uconnAffiliationLabel())
                                <span class="small">{{ $project->uconnAffiliationLabel() }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end" data-sort-col="grant_value" data-sort="{{ (float) ($project->grant_value ?? 0) }}">
                            @if($project->grant_value !== null && (float) $project->grant_value > 0)
                                <span class="small">${{ number_format((float) $project->grant_value, 2) }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-muted text-center py-4">No projects match these filters.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const table = document.getElementById('report-inventory-table');
    if (!table) { return; }

    let currentSort = table.dataset.sort || 'name';
    let currentDirection = table.dataset.direction || 'asc';

    function sortValue(row, column, type) {
        const cell = row.querySelector('[data-sort-col="' + column + '"]');
        const raw = cell ? (cell.getAttribute('data-sort') || '') : '';
        if (type === 'number') {
            const num = parseFloat(raw);
            return Number.isNaN(num) ? 0 : num;
        }
        return raw.toString().toLowerCase();
    }

    function applySort(column, direction, type) {
        const tbody = table.tBodies[0];
        const rows = Array.from(tbody.querySelectorAll('tr[data-sortable-row]'));
        rows.sort(function (a, b) {
            const av = sortValue(a, column, type);
            const bv = sortValue(b, column, type);
            if (av < bv) { return direction === 'asc' ? -1 : 1; }
            if (av > bv) { return direction === 'asc' ? 1 : -1; }
            const nameA = sortValue(a, 'name', 'text');
            const nameB = sortValue(b, 'name', 'text');
            if (nameA < nameB) { return -1; }
            if (nameA > nameB) { return 1; }
            return 0;
        });
        rows.forEach(function (row) { tbody.appendChild(row); });
    }

    function updateArrows(column, direction) {
        table.querySelectorAll('.report-sort-link').forEach(function (link) {
            const arrow = link.querySelector('.report-sort-arrow');
            if (!arrow) { return; }
            arrow.textContent = link.dataset.column === column
                ? (direction === 'desc' ? ' ↓' : ' ↑')
                : '';
        });
    }

    function syncUrlAndFilters(column, direction) {
        const url = new URL(window.location.href);
        url.searchParams.set('sort', column);
        url.searchParams.set('direction', direction);
        window.history.replaceState({}, '', url);

        const sortInput = document.querySelector('#report-filter-form input[name="sort"]');
        const dirInput = document.querySelector('#report-filter-form input[name="direction"]');
        if (sortInput) { sortInput.value = column; }
        if (dirInput) { dirInput.value = direction; }
    }

    table.querySelectorAll('.report-sort-link').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            const column = link.dataset.column;
            const type = link.dataset.sortType || 'text';
            const direction = (currentSort === column && currentDirection === 'asc') ? 'desc' : 'asc';
            currentSort = column;
            currentDirection = direction;
            applySort(column, direction, type);
            updateArrows(column, direction);
            syncUrlAndFilters(column, direction);
        });
    });
}());
</script>
@endpush
