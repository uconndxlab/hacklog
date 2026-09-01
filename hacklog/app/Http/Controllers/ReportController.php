<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\MajorOffice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public const STATUS_LABELS = [
        Project::STATUS_PLANNING => 'Planning',
        Project::STATUS_ACTIVE => 'Active',
        Project::STATUS_ON_HOLD => 'On hold',
        Project::STATUS_COMPLETED => 'Completed',
        Project::STATUS_ARCHIVED => 'Archived',
    ];

    public const STATUS_COLORS = [
        Project::STATUS_PLANNING => '#7c3aed',
        Project::STATUS_ACTIVE => '#2563eb',
        Project::STATUS_ON_HOLD => '#ea580c',
        Project::STATUS_COMPLETED => '#16a34a',
        Project::STATUS_ARCHIVED => '#6c757d',
    ];

    public function index(Request $request): View
    {
        $query = Project::query();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $status = $request->query('status');
        if (is_string($status) && in_array($status, Project::STATUS_VALUES, true)) {
            $query->where('status', $status);
        }

        $type = $request->query('type');
        if (is_string($type) && in_array($type, Project::TYPE_VALUES, true)) {
            $query->where('project_type', $type);
        }

        if ($departmentId = $request->query('department')) {
            $query->where('department_id', (int) $departmentId);
        }

        if ($officeId = $request->query('office')) {
            $query->where('major_office_id', (int) $officeId);
        }

        $affiliation = $request->query('affiliation');
        if (is_string($affiliation) && in_array($affiliation, Project::AFFILIATION_VALUES, true)) {
            $query->where('uconn_affiliation', $affiliation);
        }

        if ($request->query('grant') === '1') {
            $query->where('grant_value', '>', 0);
        } elseif ($request->query('grant') === '0') {
            $query->where(function ($grantQuery) {
                $grantQuery->whereNull('grant_value')->orWhere('grant_value', '<=', 0);
            });
        }

        $summary = (clone $query)->toBase()->select([
            DB::raw('COUNT(*) as total'),
            DB::raw('COUNT(CASE WHEN grant_value > 0 THEN 1 END) as grant_count'),
            DB::raw('COALESCE(SUM(grant_value), 0) as grant_total'),
            DB::raw('COUNT(DISTINCT department_id) as dept_count'),
            DB::raw('COUNT(DISTINCT major_office_id) as office_count'),
        ])->first();

        $projects = (clone $query)
            ->with(['department', 'nestedDepartment', 'majorOffice'])
            ->orderBy('name')
            ->get();

        $statusCounts = Project::query()
            ->select('status', DB::raw('COUNT(*) as projects_count'))
            ->groupBy('status')
            ->get()
            ->filter(fn ($row) => in_array($row->status, Project::STATUS_VALUES, true) && $row->projects_count > 0)
            ->map(fn ($row) => (object) [
                'value' => $row->status,
                'label' => self::STATUS_LABELS[$row->status] ?? $row->status,
                'projects_count' => (int) $row->projects_count,
            ])
            ->sortBy(fn ($row) => array_search($row->value, Project::STATUS_VALUES, true))
            ->values();

        $typeCounts = Project::query()
            ->whereNotNull('project_type')
            ->select('project_type', DB::raw('COUNT(*) as projects_count'))
            ->groupBy('project_type')
            ->get()
            ->filter(fn ($row) => in_array($row->project_type, Project::TYPE_VALUES, true) && $row->projects_count > 0)
            ->map(fn ($row) => (object) [
                'value' => $row->project_type,
                'label' => Project::TYPE_LABELS[$row->project_type] ?? $row->project_type,
                'projects_count' => (int) $row->projects_count,
            ])
            ->sortBy(fn ($row) => array_search($row->value, Project::TYPE_VALUES, true))
            ->values();

        return view('reports.inventory', [
            'projects' => $projects,
            'summary' => $summary,
            'statusCounts' => $statusCounts,
            'typeCounts' => $typeCounts,
            'departments' => Department::home()->orderBy('name')->get(),
            'offices' => MajorOffice::orderBy('name')->get(),
            'statusColors' => self::STATUS_COLORS,
        ]);
    }

    public function workload(Request $request): View
    {
        $validStatuses = Project::STATUS_VALUES;
        $defaultHidden = [Project::STATUS_COMPLETED, Project::STATUS_ARCHIVED];

        if ($request->has('hide')) {
            $hiddenStatuses = array_values(array_filter(
                array_map('trim', explode(',', (string) $request->query('hide', ''))),
                fn ($status) => in_array($status, $validStatuses, true)
            ));
        } else {
            $hiddenStatuses = $defaultHidden;
        }

        $users = User::query()
            ->where('active', true)
            ->where('role', '!=', User::ROLE_CLIENT)
            ->whereHas('tasks', fn ($query) => $query->where('tasks.status', '!=', 'completed'))
            ->with([
                'tasks' => fn ($query) => $query
                    ->where('tasks.status', '!=', 'completed')
                    ->with('phase.project'),
            ])
            ->orderBy('name')
            ->get();

        $presentStatuses = $users
            ->flatMap(fn (User $user) => $user->tasks->map(fn ($task) => $task->phase?->project?->status))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $rows = $users
            ->map(function (User $user) use ($hiddenStatuses) {
                $projects = $user->tasks
                    ->map(fn ($task) => $task->phase?->project)
                    ->filter()
                    ->unique('id')
                    ->values();

                $visibleCount = $projects
                    ->filter(fn ($project) => ! in_array($project->status, $hiddenStatuses, true))
                    ->count();

                return (object) [
                    'user' => $user,
                    'projects' => $projects,
                    'visible_count' => $visibleCount,
                ];
            })
            ->filter(fn ($row) => $row->visible_count > 0)
            ->sortByDesc('visible_count')
            ->values();

        return view('reports.workload', [
            'rows' => $rows,
            'hiddenStatuses' => $hiddenStatuses,
            'presentStatuses' => $presentStatuses,
            'maxCount' => $rows->max('visible_count') ?: 1,
            'statusColors' => self::STATUS_COLORS,
        ]);
    }
}
