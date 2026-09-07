<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\MajorOffice;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_inventory_editor(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        Project::create([
            'name' => 'Editor Visible Project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $this->actingAs($admin)
            ->get(route('reports.editor'))
            ->assertOk()
            ->assertSeeText('Inventory Editor')
            ->assertSee('Editor Visible Project');
    }

    public function test_team_and_client_cannot_use_inventory_editor(): void
    {
        $team = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'active' => true,
        ]);

        $this->actingAs($team)->get(route('reports.editor'))->assertForbidden();
        $this->actingAs($team)->postJson(route('reports.editor.store'))->assertForbidden();
        $this->actingAs($client)->get(route('reports.editor'))->assertForbidden();
    }

    public function test_admin_can_update_an_inventory_cell(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $department = Department::create(['name' => 'Public Policy']);
        $nested = Department::create(['name' => 'Gun Violence Center', 'parent_id' => $department->id]);
        $office = MajorOffice::create(['name' => 'CLAS']);

        $project = Project::create([
            'name' => 'ARMS Tool',
            'status' => Project::STATUS_PLANNING,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'grant_value',
                'value' => '$12,500',
            ])
            ->assertOk()
            ->assertJsonPath('project.grant_value', 12500);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'department_id',
                'value' => $department->id,
            ])
            ->assertOk()
            ->assertJsonPath('project.department_id', $department->id);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'nested_department_id',
                'value' => $nested->id,
            ])
            ->assertOk()
            ->assertJsonPath('project.nested_department_id', $nested->id);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'major_office_id',
                'value' => $office->id,
            ])
            ->assertOk()
            ->assertJsonPath('project.major_office_id', $office->id);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'grant_value' => '12500.00',
            'department_id' => $department->id,
            'nested_department_id' => $nested->id,
            'major_office_id' => $office->id,
        ]);
    }

    public function test_changing_home_department_clears_invalid_nested_department(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $home = Department::create(['name' => 'Home A']);
        $otherHome = Department::create(['name' => 'Home B']);
        $nested = Department::create(['name' => 'Nested A', 'parent_id' => $home->id]);

        $project = Project::create([
            'name' => 'Needs Rehome',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'department_id' => $home->id,
            'nested_department_id' => $nested->id,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'department_id',
                'value' => $otherHome->id,
            ])
            ->assertOk()
            ->assertJsonPath('project.department_id', $otherHome->id)
            ->assertJsonPath('project.nested_department_id', null);
    }

    public function test_nested_department_without_home_is_rejected(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $home = Department::create(['name' => 'Home']);
        $nested = Department::create(['name' => 'Nested', 'parent_id' => $home->id]);

        $project = Project::create([
            'name' => 'No Home Yet',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'nested_department_id',
                'value' => $nested->id,
            ])
            ->assertUnprocessable();
    }

    public function test_admin_can_add_a_draft_project_from_the_editor(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->postJson(route('reports.editor.store'))
            ->assertCreated()
            ->assertJsonPath('project.name', 'Untitled project')
            ->assertJsonPath('project.status', Project::STATUS_PLANNING);

        $this->assertDatabaseHas('projects', [
            'name' => 'Untitled project',
            'status' => Project::STATUS_PLANNING,
        ]);
    }

    public function test_admin_can_assign_a_project_team_from_the_editor_without_touching_client_shares(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $member = User::factory()->create(['role' => User::ROLE_TEAM, 'active' => true]);
        $removedMember = User::factory()->create(['role' => User::ROLE_TEAM, 'active' => true]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'active' => true]);
        $project = Project::create([
            'name' => 'Staffable Project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);
        $project->shares()->createMany([
            ['shareable_type' => 'user', 'shareable_id' => (string) $removedMember->id, 'is_leader' => true],
            ['shareable_type' => 'user', 'shareable_id' => (string) $client->id],
        ]);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'team_user_ids',
                'value' => [$admin->id, $member->id],
            ])
            ->assertOk()
            ->assertJsonCount(2, 'project.team_user_ids')
            ->assertJsonPath('project.leader_user_id', null);

        $this->assertDatabaseMissing('project_shares', [
            'project_id' => $project->id,
            'shareable_id' => (string) $removedMember->id,
        ]);
        $this->assertDatabaseHas('project_shares', [
            'project_id' => $project->id,
            'shareable_id' => (string) $admin->id,
        ]);
        $this->assertDatabaseHas('project_shares', [
            'project_id' => $project->id,
            'shareable_id' => (string) $member->id,
        ]);
        $this->assertDatabaseHas('project_shares', [
            'project_id' => $project->id,
            'shareable_id' => (string) $client->id,
        ]);
    }

    public function test_admin_can_set_a_project_lead_and_the_lead_is_added_to_the_team(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $lead = User::factory()->create(['role' => User::ROLE_TEAM, 'active' => true]);
        $project = Project::create([
            'name' => 'Leadership Project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'leader_user_id',
                'value' => $lead->id,
            ])
            ->assertOk()
            ->assertJsonPath('project.leader_user_id', $lead->id)
            ->assertJsonPath('project.team_user_ids.0', $lead->id);

        $this->assertDatabaseHas('project_shares', [
            'project_id' => $project->id,
            'shareable_type' => 'user',
            'shareable_id' => (string) $lead->id,
            'is_leader' => true,
        ]);
    }

    public function test_client_cannot_be_assigned_to_the_project_team_from_the_editor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'active' => true]);
        $project = Project::create([
            'name' => 'Internal Project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'team_user_ids',
                'value' => [$client->id],
            ])
            ->assertUnprocessable();

        $this->actingAs($admin)
            ->patchJson(route('reports.editor.update', $project), [
                'field' => 'leader_user_id',
                'value' => $client->id,
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('project_shares', [
            'project_id' => $project->id,
            'shareable_id' => (string) $client->id,
        ]);
    }
}
