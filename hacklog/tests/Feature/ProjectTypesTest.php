<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_user_can_manage_project_type_definitions(): void
    {
        $team = User::factory()->create(['role' => User::ROLE_TEAM, 'active' => true]);

        $this->actingAs($team)
            ->post(route('project-types.store'), [
                'name' => 'Data Visualization',
                'position' => 15,
            ])
            ->assertRedirect(route('project-types.index'));

        $type = ProjectType::where('key', 'data_visualization')->firstOrFail();

        $this->actingAs($team)
            ->put(route('project-types.update', $type), [
                'name' => 'Data Viz',
                'position' => 12,
            ])
            ->assertRedirect(route('project-types.index'));

        $this->assertDatabaseHas('project_types', [
            'key' => 'data_visualization',
            'name' => 'Data Viz',
            'position' => 12,
        ]);
    }

    public function test_custom_type_is_available_when_creating_and_editing_projects(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $type = ProjectType::create([
            'key' => 'data_visualization',
            'name' => 'Data Visualization',
            'position' => 15,
        ]);

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'name' => 'Configurable Project Type',
                'status' => Project::STATUS_ACTIVE,
                'staffing_model' => Project::STAFFING_DEDICATED,
                'project_type' => $type->key,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Configurable Project Type',
            'project_type' => 'data_visualization',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.editor'))
            ->assertOk()
            ->assertSee('Data Visualization');
    }

    public function test_renaming_a_default_type_updates_forms_without_changing_its_key(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $type = ProjectType::where('key', Project::TYPE_WEBAPP)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('project-types.update', $type), [
                'name' => 'Web Application',
                'position' => 20,
            ])
            ->assertRedirect(route('project-types.index'));

        $this->actingAs($admin)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('Web Application')
            ->assertSee('value="webapp"', false);
    }

    public function test_client_cannot_manage_types_and_used_types_cannot_be_deleted(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'active' => true]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $custom = ProjectType::create([
            'key' => 'research_tool',
            'name' => 'Research Tool',
            'position' => 60,
        ]);
        Project::create([
            'name' => 'Typed Project',
            'status' => Project::STATUS_ACTIVE,
            'project_type' => $custom->key,
        ]);

        $this->actingAs($client)->get(route('project-types.index'))->assertForbidden();
        $this->actingAs($admin)->delete(route('project-types.destroy', $custom))->assertSessionHasErrors('type');
    }

    public function test_any_unused_type_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $website = ProjectType::where('key', Project::TYPE_WEBSITE)->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('project-types.destroy', $website))
            ->assertRedirect(route('project-types.index'));

        $this->assertDatabaseMissing('project_types', ['id' => $website->id]);
    }

    public function test_orphaned_type_remains_visible_and_is_preserved_when_editing(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $type = ProjectType::create([
            'key' => 'legacy_tool',
            'name' => 'Legacy Tool',
            'position' => 60,
        ]);
        $project = Project::create([
            'name' => 'Legacy Typed Project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'project_type' => $type->key,
        ]);

        DB::table('project_types')->where('id', $type->id)->delete();

        $this->actingAs($admin)
            ->get(route('projects.edit', $project))
            ->assertOk()
            ->assertSee('Legacy Tool (unconfigured)')
            ->assertSee('value="legacy_tool" selected', false);

        $this->actingAs($admin)
            ->put(route('projects.update', $project), [
                'name' => $project->name,
                'status' => $project->status,
                'staffing_model' => $project->staffing_model,
                'project_type' => 'legacy_tool',
            ])
            ->assertRedirect(route('projects.show', $project));

        $this->assertSame('legacy_tool', $project->fresh()->project_type);
        $this->assertSame('Legacy Tool (unconfigured)', $project->fresh()->projectTypeLabel());
    }

    public function test_invalid_slug_and_key_collision_have_specific_errors(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);

        $this->actingAs($admin)
            ->post(route('project-types.store'), ['name' => '!!!', 'position' => 60])
            ->assertSessionHasErrors([
                'name' => 'Project type names must contain at least one letter or number.',
            ]);

        $this->actingAs($admin)
            ->post(route('project-types.store'), ['name' => 'Webapp!', 'position' => 60])
            ->assertSessionHasErrors([
                'name' => 'That name conflicts with the existing Webapp project type.',
            ]);
    }
}
