<?php

namespace App\Http\Controllers;

use App\Models\Phase;
use App\Models\Project;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TimelineController extends Controller
{
    /**
     * Display the organization-wide timeline.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get optional date filters from query params
        $filterStart = $request->has('start') ? Carbon::parse($request->input('start')) : null;
        $filterEnd = $request->has('end') ? Carbon::parse($request->input('end')) : null;

        $showCompleted = $request->has('show_completed') && $request->input('show_completed') === '1';

        // Build query for phases with dates, respecting project visibility
        $phasesQuery = Phase::query()
            ->whereHas('project', function ($q) use ($user) {
                // Only show phases from projects user can see
                $q->whereIn('id', Project::visibleTo($user)->pluck('id'));
            })
            ->where(function ($query) {
                $query->whereNotNull('start_date')
                      ->orWhereNotNull('end_date');
            });
        
        if (!$showCompleted) {
            $phasesQuery->where('status', '!=', 'completed');
        }

        // Apply date filters if provided
        if ($filterStart || $filterEnd) {
            $phasesQuery->where(function ($query) use ($filterStart, $filterEnd) {
                if ($filterStart && $filterEnd) {
                    // Phase overlaps with filter range
                    $query->where(function ($q) use ($filterStart, $filterEnd) {
                        $q->where(function ($q2) use ($filterStart, $filterEnd) {
                            // start_date falls within range
                            $q2->whereBetween('start_date', [$filterStart, $filterEnd]);
                        })->orWhere(function ($q2) use ($filterStart, $filterEnd) {
                            // end_date falls within range
                            $q2->whereBetween('end_date', [$filterStart, $filterEnd]);
                        })->orWhere(function ($q2) use ($filterStart, $filterEnd) {
                            // phase spans entire range
                            $q2->where('start_date', '<=', $filterStart)
                               ->where('end_date', '>=', $filterEnd);
                        });
                    });
                } elseif ($filterStart) {
                    // At least one date is on or after filter start
                    $query->where(function ($q) use ($filterStart) {
                        $q->where('start_date', '>=', $filterStart)
                          ->orWhere('end_date', '>=', $filterStart);
                    });
                } elseif ($filterEnd) {
                    // At least one date is on or before filter end
                    $query->where(function ($q) use ($filterEnd) {
                        $q->where('start_date', '<=', $filterEnd)
                          ->orWhere('end_date', '<=', $filterEnd);
                    });
                }
            });
        }

        // Eager load projects and order phases
        $phases = $phasesQuery
            ->with('project')
            ->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date', 'asc')
            ->orderBy('end_date', 'asc')
            ->get();

        // If no phases with dates, return early
        if ($phases->isEmpty()) {
            // Set default filter values for form population if not already set
            if (!$filterStart && !$filterEnd) {
                $today = Carbon::today();
                $filterStart = $today;
                $filterEnd = $today->copy()->addMonths(2);
            }
            
            return view('timeline.index', [
                'projects' => collect(),
                'phases' => $phases,
                'weeks' => [],
                'timelineStart' => $filterStart,
                'timelineEnd' => $filterEnd,
                'tooWide' => false,
                'filterStart' => $filterStart ? $filterStart->format('Y-m-d') : null,
                'filterEnd' => $filterEnd ? $filterEnd->format('Y-m-d') : null,
                'showCompleted' => $showCompleted,
            ]);
        }

        // Calculate timeline window
        if ($filterStart && $filterEnd) {
            // Use filter range
            $timelineStart = $filterStart->copy()->startOfWeek();
            $timelineEnd = $filterEnd->copy()->endOfWeek();
        } else {
            // Default to next 2 months starting today
            $today = Carbon::today();
            $timelineStart = $today->copy()->startOfWeek();
            $timelineEnd = $today->copy()->addMonths(2)->endOfWeek();
            
            // Set default filter values for form population
            $filterStart = $today;
            $filterEnd = $today->copy()->addMonths(2);
        }
        
        // Calculate number of weeks
        $weekCount = $timelineStart->diffInWeeks($timelineEnd) + 1;
        $tooWide = $weekCount > 26;

        // Generate weeks array (limit to 26 weeks if too wide)
        $weeks = [];
        $currentWeek = $timelineStart->copy();
        $displayWeeks = min($weekCount, 26);
        
        for ($i = 0; $i < $displayWeeks; $i++) {
            $weekStart = $currentWeek->copy();
            $weekEnd = $currentWeek->copy()->endOfWeek();
            
            // Format label as date range
            if ($weekStart->month === $weekEnd->month) {
                $label = $weekStart->format('M j') . '-' . $weekEnd->format('j');
            } else {
                $label = $weekStart->format('M j') . ' - ' . $weekEnd->format('M j');
            }
            
            $weeks[] = [
                'start' => $weekStart,
                'end' => $weekEnd,
                'label' => $label,
                'due_count' => 0, // Will be populated below
            ];
            $currentWeek->addWeek();
        }

        // Aggregate due dates per week
        // Count phase end_date and task due_date occurrences per week
        // Tasks use "effective due date" - explicit due_date or phase end_date fallback
        foreach ($weeks as $index => $week) {
            $dueDateCount = 0;
            
            // Count phase end dates in this week
            foreach ($phases as $phase) {
                if ($phase->end_date && 
                    $phase->end_date->gte($week['start']) && 
                    $phase->end_date->lte($week['end'])) {
                    $dueDateCount++;
                }
            }
            
            // Count task effective due dates in this week
            // Includes tasks with explicit due_date OR tasks inheriting from phase end_date
            // Include tasks from visible projects, whether they have phases or not
            $tasks = \App\Models\Task::with(['phase', 'column.project'])
                ->whereHas('column.project', function ($q) use ($user) {
                    $q->whereIn('id', Project::visibleTo($user)->pluck('id'));
                })
                ->when(!$showCompleted, function ($q) {
                    $q->where('status', '!=', 'completed');
                })
                ->get();
            
            foreach ($tasks as $task) {
                $effectiveDueDate = $task->getEffectiveDueDate();
                if ($effectiveDueDate && 
                    $effectiveDueDate->gte($week['start']) && 
                    $effectiveDueDate->lte($week['end'])) {
                    $dueDateCount++;
                }
            }
            
            $weeks[$index]['due_count'] = $dueDateCount;
        }

        // Group phases by project and add task counts per phase
        $projects = $phases->groupBy('project_id')->map(function ($projectPhases) use ($showCompleted) {
            $project = $projectPhases->first()->project;
            
            // Add task counts to each phase
            $phasesWithTaskCounts = $projectPhases->map(function ($phase) use ($showCompleted) {
                $taskCounts = $phase->tasks()
                    ->when(!$showCompleted, function ($q) {
                        $q->where('status', '!=', 'completed');
                    })
                    ->selectRaw('status, COUNT(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->toArray();
                
                // Ensure all statuses are represented
                $taskCounts = array_merge([
                    'planned' => 0,
                    'active' => 0,
                    'completed' => 0,
                ], $taskCounts);
                
                $phase->task_counts = $taskCounts;
                return $phase;
            });
            
            // Get distinct assignees for this project
            $assignees = \App\Models\User::whereHas('tasks', function ($query) use ($project, $showCompleted) {
                $query->whereHas('column.project', function ($q) use ($project) {
                    $q->where('id', $project->id);
                });
                if (!$showCompleted) {
                    $query->where('status', '!=', 'completed');
                }
            })->select('id', 'name')->distinct()->get();
            
            $assigneeCount = $assignees->count();
            
            // Create assignee data with initials and full user objects
            $assigneeData = $assignees->map(function ($user) {
                $nameParts = explode(' ', $user->name);
                return [
                    'user' => $user,
                    'initials' => strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''))
                ];
            })->sortBy('initials')->values();
            
            return [
                'project' => $project,
                'phases' => $phasesWithTaskCounts,
                'assignees' => [
                    'count' => $assigneeCount,
                    'data' => $assigneeData,
                ],
            ];
        })->values();

        return view('timeline.index', [
            'projects' => $projects,
            'phases' => $phases,
            'weeks' => $weeks,
            'timelineStart' => $timelineStart,
            'timelineEnd' => $timelineEnd,
            'tooWide' => $tooWide,
            'filterStart' => $filterStart ? $filterStart->format('Y-m-d') : null,
            'filterEnd' => $filterEnd ? $filterEnd->format('Y-m-d') : null,
            'showCompleted' => $showCompleted,
        ]);
    }
}
