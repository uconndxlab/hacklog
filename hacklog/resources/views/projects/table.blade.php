@extends('layouts.app')

@section('title', 'Projects — Table View')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-0">Projects — Table View</h1>
        <small class="text-muted">All projects across the portfolio</small>
    </div>
    <a href="{{ route('projects.index') }}" class="btn btn-outline-secondary">← Card View</a>
</div>

@if($projects->isEmpty())
    <div class="alert alert-info">No projects found.</div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 130px;">Status</th>
                        <th>Project</th>
                        <th>Type</th>
                        <th>Department</th>
                        <th>Office</th>
                        <th>Affiliation</th>
                        <th class="text-end">Grant</th>
                        <th style="width: 180px;">
                            Team
                            <span class="text-muted fw-normal" style="font-size:0.75rem;">explicit</span>
                        </th>
                        <th style="width: 180px;">
                            Contributors
                            <span class="text-muted fw-normal" style="font-size:0.75rem;">by tasks</span>
                        </th>
                        <th style="width: 200px;">Tags</th>
                        <th style="width: 130px;">Launch Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($projects as $project)
                        @php
                            // Team: explicit non-client user shares
                            $teamSharesByUser = $project->shares
                                ->where('shareable_type', 'user')
                                ->keyBy('shareable_id');

                            $team = $teamSharesByUser
                                ->map(fn($s) => $sharedUsers->get($s->shareable_id))
                                ->filter(fn($u) => $u && !$u->isClient())
                                ->unique('id')
                                ->sortBy(function ($member) use ($teamSharesByUser) {
                                    $share = $teamSharesByUser->get((string) $member->id);
                                    return [$share && $share->is_leader ? 0 : 1, $member->name];
                                });

                            // Contributors: users assigned to tasks on the project
                            $contributors = $project->columns
                                ->flatMap(fn($col) => $col->tasks)
                                ->flatMap(fn($task) => $task->users)
                                ->unique('id')
                                ->sortBy('name');
                        @endphp
                        <tr>
                            <td>
                                <span class="badge
                                    @if($project->status === 'planning') bg-info
                                    @elseif($project->status === 'active') bg-success
                                    @elseif($project->status === 'on_hold') bg-warning text-dark
                                    @elseif($project->status === 'completed') bg-primary
                                    @else bg-secondary
                                    @endif">
                                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
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
                                    <div class="small">{{ $project->department->name }}</div>
                                    @if($project->nestedDepartment)
                                        <div class="text-muted" style="font-size:0.75rem;">{{ $project->nestedDepartment->name }}</div>
                                    @endif
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
                                @if($project->grant_value !== null)
                                    <span class="small">${{ number_format((float) $project->grant_value, 2) }}</span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td>
                                @if($team->isEmpty())
                                    <span class="text-muted small">—</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($team as $member)
                                            @php
                                                $parts = explode(' ', $member->name);
                                                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                                $share = $teamSharesByUser->get((string) $member->id);
                                                $isLeader = $share && $share->is_leader;
                                            @endphp
                                            <span class="badge rounded-circle {{ $isLeader ? 'bg-primary' : 'bg-secondary' }} d-inline-flex align-items-center justify-content-center"
                                                  style="width:28px; height:28px; font-size:0.7rem;"
                                                  title="{{ $member->name }}{{ $isLeader ? ' (Project Leader)' : '' }}">
                                                {{ $initials }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($contributors->isEmpty())
                                    <span class="text-muted small">—</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($contributors as $member)
                                            @php
                                                $parts = explode(' ', $member->name);
                                                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                            @endphp
                                            <span class="badge rounded-circle bg-light border text-secondary d-inline-flex align-items-center justify-content-center"
                                                  style="width:28px; height:28px; font-size:0.7rem;"
                                                  title="{{ $member->name }}">
                                                {{ $initials }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($project->tags->isEmpty())
                                    <span class="text-muted small">—</span>
                                @else
                                    <div class="d-flex flex-wrap gap-1">
                                        @foreach($project->tags as $tag)
                                            <span class="badge rounded-pill text-bg-light border small">{{ $tag->name }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($project->launch_date)
                                    <span class="{{ $project->launch_date->isPast() && !in_array($project->status, ['completed', 'archived']) ? 'text-danger' : '' }}">
                                        {{ $project->launch_date->format('M j, Y') }}
                                    </span>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection
