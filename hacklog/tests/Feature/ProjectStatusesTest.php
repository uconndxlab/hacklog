<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectStatusesTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_user_can_manage_project_status_definitions(): void
    {
        $team = User::factory()->create(['role' => User::ROLE_TEAM, 'active' => true]);

        $this->actingAs($team)
            ->post(route('project-statuses.store'), [
                'name' => 'Discovery Review',
                'color' => '#336699',
                'position' => 15,
                'show_in_active_views' => '1',
            ])
            ->assertRedirect(route('project-statuses.index'));

        $status = ProjectStatus::where('key', 'discovery_review')->firstOrFail();

        $this->actingAs($team)
            ->put(route('project-statuses.update', $status), [
                'name' => 'Discovery',
                'color' => '#123456',
                'position' => 12,
                'show_in_active_views' => '1',
            ])
            ->assertRedirect(route('project-statuses.index'));

        $this->assertDatabaseHas('project_statuses', [
            'key' => 'discovery_review',
            'name' => 'Discovery',
            'color' => '#123456',
            'position' => 12,
            'show_in_active_views' => true,
        ]);
    }

    public function test_custom_status_is_available_when_creating_and_editing_projects(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $status = ProjectStatus::create([
            'key' => 'discovery',
            'name' => 'Discovery',
            'color' => '#336699',
            'position' => 15,
        ]);

        $this->actingAs($admin)
            ->post(route('projects.store'), [
                'name' => 'Configurable Workflow',
                'status' => $status->key,
                'staffing_model' => Project::STAFFING_DEDICATED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Configurable Workflow',
            'status' => 'discovery',
        ]);

        $this->actingAs($admin)
            ->get(route('reports.editor'))
            ->assertOk()
            ->assertSee('Discovery');
    }

    public function test_renaming_a_core_status_updates_project_forms_without_changing_its_key(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $status = ProjectStatus::where('key', Project::STATUS_ON_HOLD)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('project-statuses.update', $status), [
                'name' => 'Paused',
                'color' => '#aa5500',
                'position' => 25,
            ])
            ->assertRedirect(route('project-statuses.index'));

        $this->actingAs($admin)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('Paused')
            ->assertSee('value="on_hold"', false);

        $this->assertDatabaseHas('project_statuses', [
            'key' => Project::STATUS_ON_HOLD,
            'name' => 'Paused',
        ]);
    }

    public function test_client_cannot_manage_statuses_and_used_or_core_statuses_cannot_be_deleted(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'active' => true]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $core = ProjectStatus::where('key', Project::STATUS_ACTIVE)->firstOrFail();
        $custom = ProjectStatus::create([
            'key' => 'review',
            'name' => 'Review',
            'color' => '#336699',
            'position' => 25,
        ]);
        Project::create([
            'name' => 'Review Project',
            'status' => $custom->key,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $this->actingAs($client)->get(route('project-statuses.index'))->assertForbidden();
        $this->actingAs($admin)->delete(route('project-statuses.destroy', $core))->assertSessionHasErrors('status');
        $this->actingAs($admin)->delete(route('project-statuses.destroy', $custom))->assertSessionHasErrors('status');
    }

    public function test_active_view_setting_hides_status_from_dashboards_but_not_reports(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $activeProject = Project::create([
            'name' => 'Visible Dashboard Project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);
        $onHoldProject = Project::create([
            'name' => 'Reporting Only Project',
            'status' => Project::STATUS_ON_HOLD,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);
        $admin->favoriteProjects()->attach([$activeProject->id, $onHoldProject->id]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Visible Dashboard Project')
            ->assertDontSeeText('Reporting Only Project');

        $this->actingAs($admin)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSeeText('Reporting Only Project');

        ProjectStatus::where('key', Project::STATUS_ON_HOLD)->update(['show_in_active_views' => true]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Reporting Only Project');
    }

    public function test_empty_and_invalid_explicit_project_status_filters_are_ignored(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        Project::create([
            'name' => 'Active Filter Project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);
        Project::create([
            'name' => 'Archived Filter Project',
            'status' => Project::STATUS_ARCHIVED,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        foreach (['', 'not_a_real_status'] as $status) {
            $this->actingAs($admin)
                ->get(route('projects.index', ['status' => $status]))
                ->assertOk()
                ->assertSeeText('Active Filter Project')
                ->assertSeeText('Archived Filter Project');
        }
    }

    public function test_the_last_active_view_status_cannot_be_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        ProjectStatus::where('key', '!=', Project::STATUS_ACTIVE)->update(['show_in_active_views' => false]);
        $active = ProjectStatus::where('key', Project::STATUS_ACTIVE)->firstOrFail();

        $this->actingAs($admin)
            ->put(route('project-statuses.update', $active), [
                'name' => $active->name,
                'color' => $active->color,
                'position' => $active->position,
            ])
            ->assertSessionHasErrors('show_in_active_views');

        $this->assertTrue($active->fresh()->show_in_active_views);
    }

    public function test_active_view_values_have_a_safe_fallback_for_legacy_data(): void
    {
        ProjectStatus::query()->update(['show_in_active_views' => false]);

        $this->assertSame([Project::STATUS_ACTIVE], Project::activeViewStatusValues());
    }
}
