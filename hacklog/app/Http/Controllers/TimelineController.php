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
            // Calculate from phase dates (exclude completed phases)
            $allDates = [];
            foreach ($phases as $phase) {
                if ($phase->status === 'completed') {
                    continue;
                }
                if ($phase->start_date) {
                    $allDates[] = $phase->start_date;
                }
                if ($phase->end_date) {
                    $allDates[] = $phase->end_date;
                }
            }
            
            // If no active phase dates, use all dates
            if (empty($allDates)) {
                foreach ($phases as $phase) {
                    if ($phase->start_date) {
                        $allDates[] = $phase->start_date;
                    }
                    if ($phase->end_date) {
                        $allDates[] = $phase->end_date;
                    }
                }
            }

            $timelineStart = ($filterStart ?: min($allDates))->copy()->startOfWeek();
            $timelineEnd = ($filterEnd ?: max($allDates))->copy()->endOfWeek();
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

        // Group phases by project
        $projects = $phases->groupBy('project_id')->map(function ($projectEpics) {
            return [
                'project' => $projectEpics->first()->project,
                'phases' => $projectEpics,
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
