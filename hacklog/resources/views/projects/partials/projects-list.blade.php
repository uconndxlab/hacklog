@if($projects->isEmpty())
    @if(request()->hasAny(['search', 'scope', 'status', 'time', 'owner']))
        <div class="alert alert-info">
            <h5 class="alert-heading">No projects match your filters</h5>
            <p class="mb-0">Try adjusting your filters or <a href="{{ route('projects.index') }}" class="alert-link">view all projects</a>.</p>
        </div>
    @else
        @include('partials.empty-state', [
            'message' => 'No projects yet. Projects are top-level containers for organizing your work into phases and tasks.',
            'actionUrl' => route('projects.create'),
            'actionText' => 'Create your first project'
        ])
    @endif
@else
    <div class="row">
        @foreach($projects as $project)
            @php
                // Count active phases
                $activePhasesCount = $project->phases->where('status', '!=', 'completed')->count();
                
                // Find next relevant date (earliest upcoming phase end_date or task due_date)
                $nextDate = null;
                $today = \Carbon\Carbon::today();
                
                foreach ($project->phases as $phase) {
                    if ($phase->end_date && $phase->end_date->gte($today) && (!$nextDate || $phase->end_date->lt($nextDate))) {
                        $nextDate = $phase->end_date;
                    }
                }
                
                // Check tasks for earlier dates
                $nextTaskDate = \App\Models\Task::whereHas('phase', function($q) use ($project) {
                    $q->where('project_id', $project->id)
                      ->where('status', '!=', 'completed');
                })
                ->where('status', '!=', 'completed')
                ->where(function($q) use ($today) {
                    $q->where('due_date', '>=', $today)
                      ->orWhereHas('phase', function($phaseQ) use ($today) {
                          $phaseQ->where('end_date', '>=', $today)
                                ->whereNull('tasks.due_date');
                      });
                })
                ->orderBy('due_date', 'asc')
                ->first();
                
                if ($nextTaskDate) {
                    $taskEffectiveDate = $nextTaskDate->getEffectiveDueDate();
                    if ($taskEffectiveDate && $taskEffectiveDate->gte($today) && (!$nextDate || $taskEffectiveDate->lt($nextDate))) {
                        $nextDate = $taskEffectiveDate;
                    }
                }
            @endphp
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 project-card">
                    <div class="card-body d-flex flex-column">
                        <h2 class="h5 card-title mb-2">
                            <a href="{{ route('projects.show', $project) }}" class="text-decoration-none text-reset stretched-link">
                                {{ $project->name }}
                            </a>
                        </h2>
                        
                        <div class="mb-2">
                            <span class="badge bg-secondary bg-opacity-50 border-0" style="font-size: 0.75rem; font-weight: 400;">
                                {{ ucfirst($project->status) }}
                            </span>
                        </div>
                        
                        @if($project->description)
                            <p class="card-text text-muted small mb-3">
                                {{ Str::limit(strip_tags($project->description), 100) }}
                            </p>
                        @endif
                        
                        <div class="mt-auto small text-muted">
                            @if($activePhasesCount > 0)
                                <div class="mb-1">
                                    {{ $activePhasesCount }} active {{ Str::plural('phase', $activePhasesCount) }}
                                </div>
                            @endif
                            
                            @if($nextDate)
                                <div>
                                    Next: {{ $nextDate->format('M j, Y') }}
                                    @if($nextDate->diffInDays($today) <= 7)
                                        <span class="badge bg-warning bg-opacity-75 text-dark ms-1" style="font-size: 0.7rem;">Soon</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
