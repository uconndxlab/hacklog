<?php

namespace App\Http\Controllers;

use App\Models\Epic;
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
        // Get optional date filters from query params
        $filterStart = $request->has('start') ? Carbon::parse($request->input('start')) : null;
        $filterEnd = $request->has('end') ? Carbon::parse($request->input('end')) : null;

        $showCompleted = $request->has('show_completed') && $request->input('show_completed') === '1';

        // Build query for epics with dates
        $epicsQuery = Epic::query()
            ->whereHas('project') // Ensure project exists
            ->where(function ($query) {
                $query->whereNotNull('start_date')
                      ->orWhereNotNull('end_date');
            });
        
        if (!$showCompleted) {
            $epicsQuery->where('status', '!=', 'completed');
        }

        // Apply date filters if provided
        if ($filterStart || $filterEnd) {
            $epicsQuery->where(function ($query) use ($filterStart, $filterEnd) {
                if ($filterStart && $filterEnd) {
                    // Epic overlaps with filter range
                    $query->where(function ($q) use ($filterStart, $filterEnd) {
                        $q->where(function ($q2) use ($filterStart, $filterEnd) {
                            // start_date falls within range
                            $q2->whereBetween('start_date', [$filterStart, $filterEnd]);
                        })->orWhere(function ($q2) use ($filterStart, $filterEnd) {
                            // end_date falls within range
                            $q2->whereBetween('end_date', [$filterStart, $filterEnd]);
                        })->orWhere(function ($q2) use ($filterStart, $filterEnd) {
                            // epic spans entire range
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

        // Eager load projects and order epics
        $epics = $epicsQuery
            ->with('project')
            ->orderByRaw('CASE WHEN status = "completed" THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN start_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_date', 'asc')
            ->orderBy('end_date', 'asc')
            ->get();

        // If no epics with dates, return early
        if ($epics->isEmpty()) {
            return view('timeline.index', [
                'projects' => collect(),
                'epics' => $epics,
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
            // Calculate from epic dates (exclude completed epics)
            $allDates = [];
            foreach ($epics as $epic) {
                if ($epic->status === 'completed') {
                    continue;
                }
                if ($epic->start_date) {
                    $allDates[] = $epic->start_date;
                }
                if ($epic->end_date) {
                    $allDates[] = $epic->end_date;
                }
            }
            
            // If no active epic dates, use all dates
            if (empty($allDates)) {
                foreach ($epics as $epic) {
                    if ($epic->start_date) {
                        $allDates[] = $epic->start_date;
                    }
                    if ($epic->end_date) {
                        $allDates[] = $epic->end_date;
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
            $weeks[] = [
                'start' => $currentWeek->copy(),
                'label' => $currentWeek->format('M j'),
            ];
            $currentWeek->addWeek();
        }

        // Group epics by project
        $projects = $epics->groupBy('project_id')->map(function ($projectEpics) {
            return [
                'project' => $projectEpics->first()->project,
                'epics' => $projectEpics,
            ];
        })->values();

        return view('timeline.index', [
            'projects' => $projects,
            'epics' => $epics,
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
