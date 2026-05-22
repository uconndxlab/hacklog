<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Centralized workload aggregation service.
 * Accepts a pre-loaded collection of Task models (with 'users' eager-loaded)
 * and produces planning signals used across phase, project, and org-level views.
 */
class WorkloadSummaryService
{
    /**
     * Summarize workload from a task collection.
     * Tasks should have the 'users' relationship eager-loaded.
     */
    public static function summarize(Collection $tasks): array
    {
        $totalWeight      = 0;
        $completedWeight  = 0;
        $openHighPriority = 0;
        $unassignedHigh   = 0;
        $heavyTaskCount   = 0;

        $openTasks = $tasks->filter(fn($t) => $t->status !== 'completed');

        foreach ($tasks as $task) {
            $score = Task::WEIGHT_SCORES[$task->weight] ?? 0;
            $totalWeight += $score;
            if ($task->status === 'completed') {
                $completedWeight += $score;
            }
        }

        foreach ($openTasks as $task) {
            if ($task->priority === 'high') {
                $openHighPriority++;
                if ($task->relationLoaded('users') && $task->users->isEmpty()) {
                    $unassignedHigh++;
                }
            }
            if (in_array($task->weight, ['l', 'xl'])) {
                $heavyTaskCount++;
            }
        }

        $weightedCompletionPct = $totalWeight > 0
            ? round(($completedWeight / $totalWeight) * 100)
            : null;

        $assigneeLoad = [];
        foreach ($openTasks as $task) {
            $score = Task::WEIGHT_SCORES[$task->weight] ?? 0;
            if (!$task->relationLoaded('users')) {
                continue;
            }
            foreach ($task->users as $user) {
                if (!isset($assigneeLoad[$user->id])) {
                    $assigneeLoad[$user->id] = [
                        'user'       => $user,
                        'load'       => 0,
                        'task_count' => 0,
                        'high_count' => 0,
                    ];
                }
                $assigneeLoad[$user->id]['load']       += $score;
                $assigneeLoad[$user->id]['task_count'] += 1;
                if ($task->priority === 'high') {
                    $assigneeLoad[$user->id]['high_count']++;
                }
            }
        }

        $assigneeLoad = collect($assigneeLoad)->sortByDesc('load')->values();

        return [
            'total_weight'             => $totalWeight,
            'completed_weight'         => $completedWeight,
            'remaining_weight'         => $totalWeight - $completedWeight,
            'weighted_completion_pct'  => $weightedCompletionPct,
            'open_high_priority'       => $openHighPriority,
            'unassigned_high_priority' => $unassignedHigh,
            'heavy_task_count'         => $heavyTaskCount,
            'assignee_load'            => $assigneeLoad,
            'has_weight_data'          => $totalWeight > 0,
        ];
    }

    /**
     * Summarize workload grouped by phase.
     * Tasks must have 'phase' and 'users' eager-loaded.
     * Returns a Collection sorted by remaining_weight descending.
     */
    public static function summarizeByPhase(Collection $tasks): Collection
    {
        return $tasks
            ->groupBy('phase_id')
            ->map(function (Collection $phaseTasks) {
                $phase = $phaseTasks->first()?->phase;
                return array_merge(
                    ['phase' => $phase],
                    static::summarize($phaseTasks)
                );
            })
            ->filter(fn($s) => $s['total_weight'] > 0 || $s['open_high_priority'] > 0)
            ->sortByDesc(fn($s) => $s['remaining_weight'])
            ->values();
    }
}
