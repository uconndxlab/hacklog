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
            $query->where('projects.name', 'like', '%'.$search.'%');
        }

        $status = $request->query('status');
        if (is_string($status) && in_array($status, Project::STATUS_VALUES, true)) {
            $query->where('projects.status', $status);
        }

        $type = $request->query('type');
        if (is_string($type) && in_array($type, Project::TYPE_VALUES, true)) {
            $query->where('projects.project_type', $type);
        }

        if ($departmentId = $request->query('department')) {
            $query->where('projects.department_id', (int) $departmentId);
        }

        if ($officeId = $request->query('office')) {
            $query->where('projects.major_office_id', (int) $officeId);
        }

        $affiliation = $request->query('affiliation');
        if (is_string($affiliation) && in_array($affiliation, Project::AFFILIATION_VALUES, true)) {
            $query->where('projects.uconn_affiliation', $affiliation);
        }

        if ($request->query('grant') === '1') {
            $query->where('projects.grant_value', '>', 0);
        } elseif ($request->query('grant') === '0') {
            $query->where(function ($grantQuery) {
                $grantQuery->whereNull('projects.grant_value')->orWhere('projects.grant_value', '<=', 0);
            });
        }

        $sort = in_array($request->query('sort'), self::SORTABLE_COLUMNS, true)
            ? $request->query('sort')
            : 'name';
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $summary = (clone $query)->toBase()->select([
            DB::raw('COUNT(*) as total'),
            DB::raw('COUNT(CASE WHEN projects.grant_value > 0 THEN 1 END) as grant_count'),
            DB::raw('COALESCE(SUM(projects.grant_value), 0) as grant_total'),
            DB::raw('COUNT(DISTINCT projects.department_id) as dept_count'),
            DB::raw('COUNT(DISTINCT projects.major_office_id) as office_count'),
        ])->first();

        $listQuery = (clone $query)
            ->with(['department', 'nestedDepartment', 'majorOffice'])
            ->leftJoin('departments as home_departments', 'projects.department_id', '=', 'home_departments.id')
            ->leftJoin('departments as nested_departments', 'projects.nested_department_id', '=', 'nested_departments.id')
            ->leftJoin('major_offices', 'projects.major_office_id', '=', 'major_offices.id')
            ->select('projects.*');

        $this->applyInventorySort($listQuery, $sort, $direction);

        $projects = $listQuery->get();

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
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    private const SORTABLE_COLUMNS = [
        'status',
        'name',
        'type',
        'department',
        'nested_department',
        'office',
        'affiliation',
        'grant_value',
    ];

    private function applyInventorySort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'status' => $this->orderByEnum($query, 'projects.status', Project::STATUS_VALUES, $dir),
            'type' => $this->orderByEnum($query, 'projects.project_type', Project::TYPE_VALUES, $dir),
            'affiliation' => $this->orderByEnum($query, 'projects.uconn_affiliation', Project::AFFILIATION_VALUES, $dir),
            'department' => $query->orderBy('home_departments.name', $dir),
            'nested_department' => $query->orderBy('nested_departments.name', $dir),
            'office' => $query->orderBy('major_offices.name', $dir),
            'grant_value' => $query->orderByRaw('COALESCE(projects.grant_value, 0) '.$dir),
            default => $query->orderBy('projects.name', $dir),
        };

        if ($sort !== 'name') {
            $query->orderBy('projects.name');
        }
    }

    private function orderByEnum($query, string $column, array $values, string $direction): void
    {
        $parts = [];
        $bindings = [];

        foreach (array_values($values) as $index => $value) {
            $parts[] = 'WHEN '.$column.' = ? THEN '.$index;
            $bindings[] = $value;
        }

        $query->orderByRaw('CASE '.implode(' ', $parts).' ELSE 99 END '.$direction, $bindings);
    }

    public const WORKLOAD_BY_ASSIGNMENTS = 'assignments';

    public const WORKLOAD_BY_TEAM = 'team';

    public const WORKLOAD_BY_LEADER = 'leader';

    public const WORKLOAD_LAUNCH_ALL = 'all';

    public const WORKLOAD_LAUNCH_30 = '30';

    public const WORKLOAD_LAUNCH_60 = '60';

    public const WORKLOAD_LAUNCH_90 = '90';

    public const WORKLOAD_LAUNCH_PAST = 'past';

    public const WORKLOAD_LAUNCH_NONE = 'none';

    public const WORKLOAD_LAUNCH_OPTIONS = [
        self::WORKLOAD_LAUNCH_ALL => 'All launch dates',
        self::WORKLOAD_LAUNCH_30 => 'Next 30 days',
        self::WORKLOAD_LAUNCH_60 => 'Next 60 days',
        self::WORKLOAD_LAUNCH_90 => 'Next 90 days',
        self::WORKLOAD_LAUNCH_PAST => 'Past launch date',
        self::WORKLOAD_LAUNCH_NONE => 'No launch date',
    ];

    public function workload(Request $request): View
    {
        $by = $request->query('by', self::WORKLOAD_BY_ASSIGNMENTS);
        if (! in_array($by, [self::WORKLOAD_BY_ASSIGNMENTS, self::WORKLOAD_BY_TEAM, self::WORKLOAD_BY_LEADER], true)) {
            $by = self::WORKLOAD_BY_ASSIGNMENTS;
        }

        $hiddenStatuses = $this->workloadHiddenStatuses($request);
        $launchFilter = $this->workloadLaunchFilter($request);

        if ($by === self::WORKLOAD_BY_TEAM) {
            [$rows, $presentStatuses] = $this->workloadByProjectTeam($hiddenStatuses, $launchFilter);
        } elseif ($by === self::WORKLOAD_BY_LEADER) {
            [$rows, $presentStatuses] = $this->workloadByProjectLeader($hiddenStatuses, $launchFilter);
        } else {
            [$rows, $presentStatuses] = $this->workloadByTaskAssignments($hiddenStatuses, $launchFilter);
        }

        return view('reports.workload', [
            'by' => $by,
            'rows' => $rows,
            'hiddenStatuses' => $hiddenStatuses,
            'launchFilter' => $launchFilter,
            'presentStatuses' => $presentStatuses,
            'maxCount' => $rows->max('visible_count') ?: 1,
            'statusColors' => self::STATUS_COLORS,
        ]);
    }

    private function workloadHiddenStatuses(Request $request): array
    {
        $validStatuses = Project::STATUS_VALUES;
        $defaultHidden = [Project::STATUS_COMPLETED, Project::STATUS_ARCHIVED];

        if ($request->has('hide')) {
            return array_values(array_filter(
                array_map('trim', explode(',', (string) $request->query('hide', ''))),
                fn ($status) => in_array($status, $validStatuses, true)
            ));
        }

        return $defaultHidden;
    }

    private function workloadLaunchFilter(Request $request): string
    {
        $launch = (string) $request->query('launch', self::WORKLOAD_LAUNCH_ALL);

        return array_key_exists($launch, self::WORKLOAD_LAUNCH_OPTIONS)
            ? $launch
            : self::WORKLOAD_LAUNCH_ALL;
    }

    private function workloadMatchesLaunchFilter(Project $project, string $launchFilter): bool
    {
        if ($launchFilter === self::WORKLOAD_LAUNCH_ALL) {
            return true;
        }

        if ($launchFilter === self::WORKLOAD_LAUNCH_NONE) {
            return $project->launch_date === null;
        }

        if ($project->launch_date === null) {
            return false;
        }

        $today = today();

        return match ($launchFilter) {
            self::WORKLOAD_LAUNCH_30 => $project->launch_date->between($today, $today->copy()->addDays(30)),
            self::WORKLOAD_LAUNCH_60 => $project->launch_date->between($today, $today->copy()->addDays(60)),
            self::WORKLOAD_LAUNCH_90 => $project->launch_date->between($today, $today->copy()->addDays(90)),
            self::WORKLOAD_LAUNCH_PAST => $project->launch_date->isBefore($today)
                && ! in_array($project->status, [Project::STATUS_COMPLETED, Project::STATUS_ARCHIVED], true),
            default => true,
        };
    }

    private function workloadByTaskAssignments(array $hiddenStatuses, string $launchFilter): array
    {
        $recentlyCompletedSince = now()->subDays(20);

        $relevantTasks = function ($query) use ($recentlyCompletedSince) {
            $query->where(function ($taskQuery) use ($recentlyCompletedSince) {
                $taskQuery->where('tasks.status', '!=', 'completed')
                    ->orWhere(function ($completedQuery) use ($recentlyCompletedSince) {
                        $completedQuery->where('tasks.status', 'completed')
                            ->where('tasks.completed_at', '>=', $recentlyCompletedSince);
                    });
            });
        };

        $users = User::query()
            ->where('active', true)
            ->where('role', '!=', User::ROLE_CLIENT)
            ->whereHas('tasks', $relevantTasks)
            ->with([
                'tasks' => function ($query) use ($relevantTasks) {
                    $relevantTasks($query);
                    $query->with(['phase.project', 'column.project']);
                },
            ])
            ->orderBy('name')
            ->get();

        $presentStatuses = $users
            ->flatMap(fn (User $user) => $user->tasks->map(fn ($task) => $this->projectForTask($task)?->status))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $rows = $users
            ->map(function (User $user) use ($hiddenStatuses, $launchFilter) {
                $projects = $user->tasks
                    ->map(fn ($task) => $this->projectForTask($task))
                    ->filter()
                    ->unique('id')
                    ->values();

                return $this->workloadRow($user, $projects, $hiddenStatuses, $launchFilter);
            })
            ->filter(fn ($row) => $row->visible_count > 0);

        return [$this->sortWorkloadRows($rows, $launchFilter), $presentStatuses];
    }

    private function workloadByProjectTeam(array $hiddenStatuses, string $launchFilter): array
    {
        $users = User::query()
            ->where('active', true)
            ->where('role', '!=', User::ROLE_CLIENT)
            ->whereHas('projectShares')
            ->with(['projectShares.project'])
            ->orderBy('name')
            ->get();

        $presentStatuses = $users
            ->flatMap(fn (User $user) => $user->projectShares->map(fn ($share) => $share->project?->status))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $rows = $users
            ->map(function (User $user) use ($hiddenStatuses, $launchFilter) {
                $projects = $user->projectShares
                    ->map(fn ($share) => $share->project)
                    ->filter()
                    ->unique('id')
                    ->values();

                return $this->workloadRow($user, $projects, $hiddenStatuses, $launchFilter);
            })
            ->filter(fn ($row) => $row->visible_count > 0);

        return [$this->sortWorkloadRows($rows, $launchFilter), $presentStatuses];
    }

    private function workloadByProjectLeader(array $hiddenStatuses, string $launchFilter): array
    {
        $users = User::query()
            ->where('active', true)
            ->where('role', '!=', User::ROLE_CLIENT)
            ->whereHas('projectShares', fn ($query) => $query->where('is_leader', true))
            ->with(['projectShares' => fn ($query) => $query->where('is_leader', true)->with('project')])
            ->orderBy('name')
            ->get();

        $presentStatuses = $users
            ->flatMap(fn (User $user) => $user->projectShares->map(fn ($share) => $share->project?->status))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $rows = $users
            ->map(function (User $user) use ($hiddenStatuses, $launchFilter) {
                $projects = $user->projectShares
                    ->map(fn ($share) => $share->project)
                    ->filter()
                    ->unique('id')
                    ->values();

                return $this->workloadRow($user, $projects, $hiddenStatuses, $launchFilter);
            })
            ->filter(fn ($row) => $row->visible_count > 0);

        return [$this->sortWorkloadRows($rows, $launchFilter), $presentStatuses];
    }

    private function workloadRow(User $user, $projects, array $hiddenStatuses, string $launchFilter): object
    {
        $visibleProjects = $projects->filter(function ($project) use ($hiddenStatuses, $launchFilter) {
            if (in_array($project->status, $hiddenStatuses, true)) {
                return false;
            }

            return $this->workloadMatchesLaunchFilter($project, $launchFilter);
        })->values();

        return (object) [
            'user' => $user,
            'projects' => $visibleProjects,
            'visible_count' => $visibleProjects->count(),
            'nearest_launch' => $visibleProjects->min('launch_date'),
        ];
    }

    private function sortWorkloadRows($rows, string $launchFilter)
    {
        if ($launchFilter !== self::WORKLOAD_LAUNCH_ALL) {
            return $rows
                ->sortBy([
                    fn ($row) => $row->nearest_launch === null ? 1 : 0,
                    fn ($row) => $row->nearest_launch,
                    fn ($row) => $row->user->name,
                ])
                ->values();
        }

        return $rows->sortByDesc('visible_count')->values();
    }

    private function projectForTask($task): ?Project
    {
        return $task->phase?->project ?? $task->column?->project;
    }
}
