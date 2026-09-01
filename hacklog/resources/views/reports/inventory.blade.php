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
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Status</th>
                    <th>Project</th>
                    <th>Type</th>
                    <th>Department</th>
                    <th>Nested</th>
                    <th>Office</th>
                    <th>Affiliation</th>
                    <th class="text-end">Grant</th>
                </tr>
            </thead>
            <tbody>
                @forelse($projects as $project)
                    <tr>
                        <td>
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
                        <td>
                            <a href="{{ route('projects.show', $project) }}" class="fw-semibold text-decoration-none">
                                {{ $project->name }}
                            </a>
                        </td>
                        <td>
                            @if($project->projectTypeLabel())
                                <span class="small">{{ $project->projectTypeLabel() }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($project->department)
                                <span class="small">{{ $project->department->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($project->nestedDepartment)
                                <span class="small">{{ $project->nestedDepartment->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($project->majorOffice)
                                <span class="small">{{ $project->majorOffice->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>
                            @if($project->uconnAffiliationLabel())
                                <span class="small">{{ $project->uconnAffiliationLabel() }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td class="text-end">
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
