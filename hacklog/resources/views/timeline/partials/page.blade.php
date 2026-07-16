<div id="timeline-page">
    @php
        $selectedProjectNames = collect($filters['project_ids'])->map(fn ($id) => $filterOptions['projects']->firstWhere('id', $id)?->name)->filter()->values();
        $selectedTagNames = collect($filters['tag_ids'])->map(fn ($id) => $filterOptions['tags']->firstWhere('id', $id)?->name)->filter()->values();
        $selectedAssigneeNames = collect($filters['assignee_ids'])->map(fn ($id) => $filterOptions['assignees']->firstWhere('id', $id)?->name)->filter()->values();

        $activeChips = collect();
        if ($filters['has_start'] && $filters['start']) {
            $activeChips->push('From: ' . \Carbon\Carbon::parse($filters['start'])->format('M j, Y'));
        }
        if ($filters['has_end'] && $filters['end']) {
            $activeChips->push('To: ' . \Carbon\Carbon::parse($filters['end'])->format('M j, Y'));
        }
        if ($filters['has_project_ids'] && $selectedProjectNames->isNotEmpty()) {
            $activeChips->push('Projects: ' . $selectedProjectNames->take(2)->implode(', ') . ($selectedProjectNames->count() > 2 ? ' +' . ($selectedProjectNames->count() - 2) : ''));
        }
        if ($filters['has_project_statuses']) {
            $activeChips->push('Project status: ' . collect($filters['project_statuses'])->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->implode(', '));
        }
        if ($filters['has_phase_statuses']) {
            $activeChips->push('Phase status: ' . collect($filters['phase_statuses'])->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->implode(', '));
        }
        if ($filters['has_task_statuses']) {
            $activeChips->push('Task status: ' . collect($filters['task_statuses'])->map(fn ($status) => ucfirst(str_replace('_', ' ', $status)))->implode(', '));
        }
        if ($filters['has_assignee_ids'] && $selectedAssigneeNames->isNotEmpty()) {
            $activeChips->push('Assignees: ' . $selectedAssigneeNames->take(2)->implode(', ') . ($selectedAssigneeNames->count() > 2 ? ' +' . ($selectedAssigneeNames->count() - 2) : ''));
        }
        if ($filters['has_tag_ids'] && $selectedTagNames->isNotEmpty()) {
            $activeChips->push('Tags: ' . $selectedTagNames->take(2)->implode(', ') . ($selectedTagNames->count() > 2 ? ' +' . ($selectedTagNames->count() - 2) : ''));
        }
        if ($filters['has_staffing_models']) {
            $activeChips->push('Staffing: ' . collect($filters['staffing_models'])->map(fn ($model) => ucfirst($model))->implode(', '));
        }
        if ($filters['show_completed']) {
            $activeChips->push('Including completed work');
        }
        if ($filters['overdue_only']) {
            $activeChips->push('Overdue only');
        }
    @endphp

    <div class="card mb-4">
        <div class="card-body py-2">
            <form
                id="timeline-filters"
                method="GET"
                action="{{ route('timeline.index') }}"
                class="timeline-filter-bar d-flex align-items-center gap-2 flex-wrap"
                hx-get="{{ route('timeline.index') }}"
                hx-target="#timeline-page"
                hx-swap="outerHTML"
                hx-push-url="true"
                hx-trigger="change delay:120ms, submit">
                <div class="d-flex align-items-center gap-2">
                    <label for="timeline-start" class="form-label mb-0 text-nowrap">From</label>
                    <input type="date" class="form-control form-control-sm" id="timeline-start" name="start" value="{{ $filters['start'] }}">
                </div>

                <div class="d-flex align-items-center gap-2">
                    <label for="timeline-end" class="form-label mb-0 text-nowrap">To</label>
                    <input type="date" class="form-control form-control-sm" id="timeline-end" name="end" value="{{ $filters['end'] }}">
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        Projects @if(!empty($filters['project_ids'])) ({{ count($filters['project_ids']) }}) @endif
                    </button>
                    <div class="dropdown-menu p-3 timeline-filter-menu">
                        @foreach($filterOptions['projects'] as $projectOption)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="project-filter-{{ $projectOption->id }}" name="project_ids[]" value="{{ $projectOption->id }}" {{ in_array($projectOption->id, $filters['project_ids'], true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="project-filter-{{ $projectOption->id }}">{{ $projectOption->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        Project Status @if($filters['has_project_statuses']) ({{ count($filters['project_statuses']) }}) @endif
                    </button>
                    <div class="dropdown-menu p-3 timeline-filter-menu">
                        @foreach($filterOptions['project_statuses'] as $status)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="project-status-filter-{{ $status }}" name="project_statuses[]" value="{{ $status }}" {{ in_array($status, $filters['project_statuses'], true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="project-status-filter-{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        Phase Status @if($filters['has_phase_statuses']) ({{ count($filters['phase_statuses']) }}) @endif
                    </button>
                    <div class="dropdown-menu p-3 timeline-filter-menu">
                        @foreach($filterOptions['phase_statuses'] as $status)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="phase-status-filter-{{ $status }}" name="phase_statuses[]" value="{{ $status }}" {{ in_array($status, $filters['phase_statuses'], true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="phase-status-filter-{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        Task Status @if($filters['has_task_statuses']) ({{ count($filters['task_statuses']) }}) @endif
                    </button>
                    <div class="dropdown-menu p-3 timeline-filter-menu">
                        @foreach($filterOptions['task_statuses'] as $status)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="task-status-filter-{{ $status }}" name="task_statuses[]" value="{{ $status }}" {{ in_array($status, $filters['task_statuses'], true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="task-status-filter-{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        Assignees @if(!empty($filters['assignee_ids'])) ({{ count($filters['assignee_ids']) }}) @endif
                    </button>
                    <div class="dropdown-menu p-3 timeline-filter-menu">
                        @foreach($filterOptions['assignees'] as $assigneeOption)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="assignee-filter-{{ $assigneeOption->id }}" name="assignee_ids[]" value="{{ $assigneeOption->id }}" {{ in_array($assigneeOption->id, $filters['assignee_ids'], true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="assignee-filter-{{ $assigneeOption->id }}">{{ $assigneeOption->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        Tags @if(!empty($filters['tag_ids'])) ({{ count($filters['tag_ids']) }}) @endif
                    </button>
                    <div class="dropdown-menu p-3 timeline-filter-menu">
                        @foreach($filterOptions['tags'] as $tagOption)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="tag-filter-{{ $tagOption->id }}" name="tag_ids[]" value="{{ $tagOption->id }}" {{ in_array($tagOption->id, $filters['tag_ids'], true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="tag-filter-{{ $tagOption->id }}">{{ $tagOption->name }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        Staffing @if($filters['has_staffing_models']) ({{ count($filters['staffing_models']) }}) @endif
                    </button>
                    <div class="dropdown-menu p-3 timeline-filter-menu">
                        @foreach($filterOptions['staffing_models'] as $model)
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="staffing-filter-{{ $model }}" name="staffing_models[]" value="{{ $model }}" {{ in_array($model, $filters['staffing_models'], true) ? 'checked' : '' }}>
                                <label class="form-check-label" for="staffing-filter-{{ $model }}">{{ ucfirst($model) }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="form-check form-switch ms-1">
                    <input class="form-check-input" type="checkbox" id="timeline-show-completed" name="show_completed" value="1" {{ $filters['show_completed'] ? 'checked' : '' }}>
                    <label class="form-check-label small" for="timeline-show-completed">Completed</label>
                </div>

                <div class="form-check form-switch ms-1">
                    <input class="form-check-input" type="checkbox" id="timeline-overdue-only" name="overdue_only" value="1" {{ $filters['overdue_only'] ? 'checked' : '' }}>
                    <label class="form-check-label small" for="timeline-overdue-only">Overdue Only</label>
                </div>

                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                <a href="{{ route('timeline.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </form>

            @if($activeChips->isNotEmpty())
                <div class="timeline-filter-chips mt-2 d-flex align-items-center gap-1 flex-wrap">
                    @foreach($activeChips as $chip)
                        <span class="badge rounded-pill border text-muted bg-transparent fw-normal">{{ $chip }}</span>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if($phases->isEmpty())
        @if($filters['has_start'] || $filters['has_end'])
            @include('partials.empty-state', [
                'message' => 'No phases found in the selected filters. Try adjusting your filters or clearing them to see all phases.'
            ])
        @else
            @include('partials.empty-state', [
                'message' => 'No phases with dates yet. Create phases in your projects and add start or end dates to see them here.',
                'actionUrl' => route('projects.index'),
                'actionText' => 'View projects'
            ])
        @endif
    @else
        @if($tooWide)
            <div class="alert alert-warning mb-3">
                <p class="mb-0">Timeline spans more than 26 weeks. Showing first 26 weeks only. Use date filters to narrow the range.</p>
            </div>
        @endif

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 timeline-table">
                        <thead>
                            <tr>
                                <th style="width: 120px;" class="timeline-header">Project</th>
                                <th style="width: 150px;" class="timeline-header">Phase</th>
                                @foreach($weeks as $week)
                                    @php
                                        $dueCount = $week['due_count'];
                                        $heatClass = '';
                                        if ($dueCount >= 4) {
                                            $heatClass = 'timeline-heat-high';
                                        } elseif ($dueCount >= 2) {
                                            $heatClass = 'timeline-heat-medium';
                                        } elseif ($dueCount >= 1) {
                                            $heatClass = 'timeline-heat-low';
                                        }
                                    @endphp
                                    <th class="text-center timeline-header {{ $heatClass }}" style="width: 95px;" title="{{ $dueCount }} {{ Str::plural('due date', $dueCount) }} this week">
                                        <small class="text-muted">{{ $week['label'] }}</small>
                                        @if($dueCount > 0)
                                            <br><span class="badge bg-secondary bg-opacity-50 rounded-pill" style="font-size: 0.7rem;">{{ $dueCount }}</span>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $lastProjectId = null;
                            @endphp
                            @foreach($projects as $projectGroup)
                                @php
                                    $project = $projectGroup['project'];
                                    $projectPhases = $projectGroup['phases'];
                                @endphp
                                @foreach($projectPhases as $phase)
                                    @php
                                        $phaseStart = $phase->start_date ?: $phase->end_date;
                                        $phaseEnd = $phase->end_date ?: $phase->start_date;

                                        $cellStates = [];
                                        foreach ($weeks as $index => $week) {
                                            $weekEnd = $week['start']->copy()->endOfWeek();

                                            if ($phaseStart->lte($weekEnd) && $phaseEnd->gte($week['start'])) {
                                                $cellStates[$index] = 'filled';
                                            } else {
                                                $cellStates[$index] = 'empty';
                                            }
                                        }

                                        $showProjectName = $lastProjectId !== $project->id;
                                        $lastProjectId = $project->id;
                                    @endphp
                                    <tr class="@if($phase->status === 'completed') bg-light @endif">
                                        <td class="@if($phase->status === 'completed') bg-light @endif">
                                            @if($showProjectName)
                                                <div class="d-flex flex-column gap-1">
                                                    <a href="{{ route('projects.show', $project) }}" class="text-decoration-none @if($phase->status === 'completed') text-muted @endif" style="font-size: 0.9375rem;">
                                                        {{ $project->name }}
                                                    </a>
                                                    <div>
                                                        <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.7rem; font-weight: 400;">
                                                            {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                                        </span>
                                                    </div>
                                                    @if($projectGroup['assignees']['count'] > 0)
                                                        <div class="d-flex flex-wrap gap-1">
                                                            @foreach($projectGroup['assignees']['data'] as $assignee)
                                                                <span class="badge bg-info text-dark border-0" style="font-size: 0.75rem; font-weight: 500; cursor:pointer;" title="{{ $assignee['user']->name }}">
                                                                    {{ $assignee['initials'] }}
                                                                </span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="timeline-phase-label @if($phase->isOverdue()) timeline-phase-overdue @elseif($phase->status === 'completed') bg-light @endif">
                                            <div class="d-flex flex-column gap-1">
                                                <a href="{{ route('projects.board', ['project' => $project, 'phase' => $phase->id]) }}" class="@if($phase->isOverdue()) timeline-phase-overdue-text @elseif($phase->status === 'completed') text-muted @else text-body @endif text-decoration-none" style="font-size: 0.875rem;">
                                                    {{ $phase->name }}
                                                </a>
                                                <div class="d-flex flex-column gap-1">
                                                    <div>
                                                        <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.7rem; font-weight: 400;">
                                                            {{ ucfirst(str_replace('_', ' ', $phase->status)) }}
                                                        </span>
                                                    </div>
                                                    @if(($phase->task_counts['planned'] ?? 0) > 0 || ($phase->task_counts['active'] ?? 0) > 0 || ($phase->task_counts['awaiting_feedback'] ?? 0) > 0 || ($phase->task_counts['completed'] ?? 0) > 0)
                                                        <div class="d-flex flex-wrap gap-1 mt-1">
                                                            @if(($phase->task_counts['planned'] ?? 0) > 0)
                                                                <small class="badge bg-secondary" style="font-size: 0.65rem;">{{ $phase->task_counts['planned'] }} Planned</small>
                                                            @endif
                                                            @if(($phase->task_counts['active'] ?? 0) > 0)
                                                                <small class="badge bg-success" style="font-size: 0.65rem;">{{ $phase->task_counts['active'] }} Active</small>
                                                            @endif
                                                            @if(($phase->task_counts['awaiting_feedback'] ?? 0) > 0)
                                                                <small class="badge bg-info text-dark" style="font-size: 0.65rem;">{{ $phase->task_counts['awaiting_feedback'] }} Awaiting Feedback</small>
                                                            @endif
                                                            @if(($phase->task_counts['completed'] ?? 0) > 0)
                                                                <small class="badge bg-light text-dark" style="font-size: 0.65rem;">{{ $phase->task_counts['completed'] }} Completed</small>
                                                            @endif
                                                        </div>
                                                    @endif
                                                    <div class="text-muted" style="font-size: 0.8125rem; line-height: 1.3;">
                                                        @if($phase->start_date && $phase->end_date)
                                                            {{ $phase->start_date->format('M j') }} - {{ $phase->end_date->format('M j, Y') }}
                                                        @elseif($phase->start_date)
                                                            Start: {{ $phase->start_date->format('M j, Y') }}
                                                        @else
                                                            End: {{ $phase->end_date->format('M j, Y') }}
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        @foreach($cellStates as $state)
                                            <td class="timeline-cell @if($state === 'filled') @if($phase->isOverdue()) timeline-bar-overdue @elseif($phase->status === 'completed') timeline-bar-completed @else timeline-bar-active @endif @endif" style="width: 95px;">
                                                @if($state === 'filled')
                                                    &nbsp;
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            <small class="text-muted">
                Timeline shows weeks from {{ $timelineStart->format('M j, Y') }} to {{ $timelineEnd->format('M j, Y') }}
                @if($tooWide)
                    (first 26 weeks)
                @endif
                @if($filters['has_start'] || $filters['has_end'])
                    - filtered view
                @endif
            </small>
        </div>
    @endif
</div>
