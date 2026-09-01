<?php

namespace Tests\Feature;

use App\Models\Column;
use App\Models\Department;
use App\Models\MajorOffice;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_inventory_report(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSeeText('Project Inventory');
        $response->assertSeeText('Filtered Summary');
    }

    public function test_team_user_cannot_view_reports(): void
    {
        $team = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $this->actingAs($team)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($team)->get(route('reports.workload'))->assertForbidden();
    }

    public function test_client_cannot_view_reports(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'active' => true,
        ]);

        $this->actingAs($client)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($client)->get(route('reports.workload'))->assertForbidden();
    }

    public function test_inventory_filters_affect_summary_but_charts_stay_global(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $department = Department::create(['name' => 'ITS']);
        $office = MajorOffice::create(['name' => 'University IT']);

        Project::create([
            'name' => 'Funded Website Alpha',
            'description' => 'Grant project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'project_type' => Project::TYPE_WEBSITE,
            'department_id' => $department->id,
            'major_office_id' => $office->id,
            'grant_value' => 15000,
        ]);

        Project::create([
            'name' => 'Unfunded Program Beta',
            'description' => 'No grant',
            'status' => Project::STATUS_PLANNING,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'project_type' => Project::TYPE_PROGRAM,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index', [
            'type' => Project::TYPE_WEBSITE,
            'grant' => '1',
        ]));

        $response->assertOk();
        $response->assertSeeText('Funded Website Alpha');
        $response->assertDontSeeText('Unfunded Program Beta');
        $response->assertViewHas('summary', function ($summary) {
            return (int) $summary->total === 1
                && (int) $summary->grant_count === 1
                && (float) $summary->grant_total == 15000.0
                && (int) $summary->dept_count === 1
                && (int) $summary->office_count === 1;
        });
        $response->assertViewHas('statusCounts', function ($counts) {
            return (int) $counts->sum('projects_count') === 2;
        });
        $response->assertViewHas('typeCounts', function ($counts) {
            return (int) $counts->sum('projects_count') === 2;
        });
    }

    public function test_admin_can_view_workload_for_incomplete_task_assignees(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Reporter',
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $assignee = User::factory()->create([
            'name' => 'Active Assignee',
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $completedOnly = User::factory()->create([
            'name' => 'Finished Assignee',
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $project = Project::create([
            'name' => 'Workload Project',
            'description' => 'Has open work',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $phase = Phase::create([
            'project_id' => $project->id,
            'name' => 'Build',
            'status' => 'active',
        ]);

        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Backlog',
            'position' => 1,
            'is_default' => true,
        ]);

        $openTask = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Open work',
            'status' => 'active',
            'position' => 1,
        ]);
        $openTask->users()->attach($assignee->id);

        $doneTask = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Done work',
            'status' => 'completed',
            'position' => 2,
        ]);
        $doneTask->users()->attach($completedOnly->id);

        $response = $this->actingAs($admin)->get(route('reports.workload'));

        $response->assertOk();
        $response->assertSeeText('Staff Workload');
        $response->assertSeeText('Active Assignee');
        $response->assertSee('Workload Project');
        $response->assertDontSeeText('Finished Assignee');
    }

    public function test_inventory_headers_sort_projects(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        Project::create([
            'name' => 'Zebra Report Project',
            'description' => 'Second alphabetically',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'grant_value' => 100,
        ]);

        Project::create([
            'name' => 'Alpha Report Project',
            'description' => 'First alphabetically',
            'status' => Project::STATUS_PLANNING,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'grant_value' => 500,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.index', ['sort' => 'name', 'direction' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Report Project', 'Zebra Report Project']);

        $this->actingAs($admin)
            ->get(route('reports.index', ['sort' => 'name', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Zebra Report Project', 'Alpha Report Project']);

        $this->actingAs($admin)
            ->get(route('reports.index', ['sort' => 'grant_value', 'direction' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Report Project', 'Zebra Report Project']);
    }
}
