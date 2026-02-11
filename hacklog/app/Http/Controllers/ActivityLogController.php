<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    /**
     * Display the organization-wide activity log
     */
    public function index(Request $request)
    {
        // Ensure user is admin
        if (!$request->user()->isAdmin()) {
            abort(403, 'Only administrators can view the activity log.');
        }

        // Parse date filters with defaults
        $filterStart = $request->has('start') && !empty($request->input('start'))
            ? Carbon::parse($request->input('start'))->startOfDay()
            : Carbon::today()->subDays(7);

        $filterEnd = $request->has('end') && !empty($request->input('end'))
            ? Carbon::parse($request->input('end'))->endOfDay()
            : Carbon::today()->endOfDay();

        // Get all projects for filter dropdown
        $projects = Project::orderBy('name')->get();

        // Get all users for filter dropdown
        $users = User::orderBy('name')->get();

        // Build project activities query
        $projectActivitiesQuery = ProjectActivity::select('id', 'project_id', 'user_id', 'action', 'metadata', 'created_at', DB::raw("'project' as type"))
            ->whereBetween('created_at', [$filterStart, $filterEnd])
            ->orderBy('created_at', 'desc');

        // Build task activities query
        $taskActivitiesQuery = TaskActivity::select('id', 'task_id', 'user_id', 'action', 'metadata', 'created_at', DB::raw("'task' as type"))
            ->whereBetween('created_at', [$filterStart, $filterEnd])
            ->orderBy('created_at', 'desc');

        // Build task comments query
        $taskCommentsQuery = TaskComment::select('id', 'task_id', 'user_id', 'body', 'created_at', DB::raw("'comment' as type"))
            ->whereBetween('created_at', [$filterStart, $filterEnd])
            ->orderBy('created_at', 'desc');

        // Filter by project (only if a valid project ID is provided)
        if ($request->filled('project_id') && $request->input('project_id') != '') {
            $projectId = $request->input('project_id');
            $projectActivitiesQuery->where('project_id', $projectId);
            $taskActivitiesQuery->whereHas('task.column', function ($q) use ($projectId) {
                $q->where('project_id', $projectId);
            });
            $taskCommentsQuery->whereHas('task.column', function ($q) use ($projectId) {
                $q->where('project_id', $projectId);
            });
        }

        // Filter by user (only if a valid user ID is provided)
        $userFilterApplied = false;
        $userId = $request->input('user_id');
        if ($userId && $userId !== '') {
            $userId = $userId; // Keep as string for comparison
            $projectActivitiesQuery->where('user_id', $userId);
            $taskActivitiesQuery->where('user_id', $userId);
            $taskCommentsQuery->where('user_id', $userId);
            $userFilterApplied = true;
        }

        // Filter by activity type (only if a valid type is provided)
        if ($request->filled('type') && $request->input('type') != '') {
            $type = $request->input('type');
            if ($type === 'project') {
                $taskActivitiesQuery->whereRaw('1 = 0'); // Exclude all task activities
                $taskCommentsQuery->whereRaw('1 = 0'); // Exclude all comments
            } elseif ($type === 'task') {
                $projectActivitiesQuery->whereRaw('1 = 0'); // Exclude all project activities
                $taskCommentsQuery->whereRaw('1 = 0'); // Exclude all comments
            } elseif ($type === 'comment') {
                $projectActivitiesQuery->whereRaw('1 = 0'); // Exclude all project activities
                $taskActivitiesQuery->whereRaw('1 = 0'); // Exclude all task activities
            }
        }

        // Get activities
        $projectActivities = $projectActivitiesQuery->get()->load(['project', 'user']);
        $taskActivities = $taskActivitiesQuery->get()->load(['task.column.project', 'user']);
        $taskComments = $taskCommentsQuery->get()->load(['task.column.project', 'task.phase', 'user']);

        // Initialize new activities collections
        $newProjectActivities = collect([]);
        $newTaskActivities = collect([]);

        // Debug activities
        \Log::info('Activities query results', [
            'project_activities_count' => $projectActivities->count(),
            'task_activities_count' => $taskActivities->count(),
            'task_comments_count' => $taskComments->count(),
            'new_project_activities_count' => $newProjectActivities->count(),
            'new_task_activities_count' => $newTaskActivities->count(),
            'total_activities' => $projectActivities->count() + $taskActivities->count() + $taskComments->count() + $newProjectActivities->count() + $newTaskActivities->count(),
            'project_activities_sample' => $projectActivities->take(2)->map(function($a) {
                return [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'action' => $a->action,
                    'created_at' => $a->created_at->format('Y-m-d H:i:s')
                ];
            }),
            'task_activities_sample' => $taskActivities->take(2)->map(function($a) {
                return [
                    'id' => $a->id,
                    'user_id' => $a->user_id,
                    'action' => $a->action,
                    'created_at' => $a->created_at->format('Y-m-d H:i:s')
                ];
            })
        ]);

        // Debug: Check if task activities exist
        if ($userFilterApplied && $taskActivities->isEmpty()) {
            // Check if there are any task activities at all for this date range
            $allTaskActivities = TaskActivity::whereBetween('created_at', [$filterStart, $filterEnd])->get();
            \Log::info('Task Activities Debug', [
                'user_filter_applied' => true,
                'user_id' => $request->input('user_id'),
                'filtered_task_activities_count' => $taskActivities->count(),
                'all_task_activities_count' => $allTaskActivities->count(),
                'sample_task_activity' => $allTaskActivities->first() ? [
                    'id' => $allTaskActivities->first()->id,
                    'user_id' => $allTaskActivities->first()->user_id,
                    'action' => $allTaskActivities->first()->action,
                ] : null
            ]);
        }

        // Get project IDs that already have a "created" activity logged
        $projectIdsWithCreatedActivity = ProjectActivity::where('action', 'created')
            ->whereBetween('created_at', [$filterStart, $filterEnd])
            ->pluck('project_id')
            ->toArray();

        // Also get projects created during this date range (even if no activities logged yet)
        // Only show these when NOT filtering by user (since they have no user_id)
        // Also exclude when filtering by task or comment type
        
        $typeFilter = $request->input('type');
        if ((!$request->filled('user_id') || $request->input('user_id') == '') 
            && (!$request->filled('type') || $typeFilter == '' || $typeFilter === 'project')) {
            $newProjectsQuery = Project::whereBetween('created_at', [$filterStart, $filterEnd])
                ->whereNotIn('id', $projectIdsWithCreatedActivity);

            // Apply same project filter
            if ($request->filled('project_id') && $request->input('project_id') != '') {
                $newProjectsQuery->where('id', $request->input('project_id'));
            }

            // Convert new projects to activity-like objects
            $newProjectActivities = $newProjectsQuery->get()->map(function($project) {
                return (object)[
                    'id' => 'project_created_' . $project->id,
                    'project_id' => $project->id,
                    'project' => $project,
                    'user_id' => null,
                    'user' => null,
                    'action' => 'created',
                    'metadata' => null,
                    'created_at' => $project->created_at,
                    'type' => 'project',
                ];
            });
        }

        // Also get tasks created during this date range
        // Include when NOT filtering by type or when type is 'task'
        $newTaskActivities = collect([]);
        if (!$request->filled('type') || $request->input('type') == '' || $request->input('type') === 'task') {
            $newTasksQuery = Task::whereBetween('created_at', [$filterStart, $filterEnd])
                ->with(['column.project', 'phase', 'creator']); // Load relationships

            // Apply project filter
            if ($request->filled('project_id') && $request->input('project_id') != '') {
                $newTasksQuery->whereHas('column', function ($q) use ($request) {
                    $q->where('project_id', $request->input('project_id'));
                });
            }

            // Apply user filter (tasks have created_by)
            if ($request->filled('user_id') && $request->input('user_id') != '') {
                $newTasksQuery->where('created_by', $request->input('user_id'));
            }

            // Convert new tasks to activity-like objects
            $newTaskActivities = $newTasksQuery->get()->map(function($task) {
                return (object)[
                    'id' => 'task_created_' . $task->id,
                    'task_id' => $task->id,
                    'task' => $task,
                    'user_id' => $task->created_by,
                    'user' => $task->creator,
                    'action' => 'created',
                    'metadata' => null,
                    'created_at' => $task->created_at,
                    'type' => 'task',
                ];
            });
        }

        // Combine and sort by created_at
        $allActivities = $projectActivities
            ->concat($taskActivities)
            ->concat($taskComments)
            ->concat($newProjectActivities)
            ->concat($newTaskActivities)
            ->sortByDesc('created_at')
            ->take(200); // Show most recent 200 activities

        return view('activity-log.index', compact('allActivities', 'filterStart', 'filterEnd', 'projects', 'users'));
    }
}
