<?php

namespace Tests\Feature;

use App\Models\Column;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_filters_by_project_status_and_staffing_model(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $matchingProject = Project::create([
            'name' => 'Roadmap Project',
            'description' => 'Visible in active dedicated filter',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $filteredOutProject = Project::create([
            'name' => 'Archived Shared Project',
            'description' => 'Should not appear',
            'status' => Project::STATUS_ARCHIVED,
            'staffing_model' => Project::STAFFING_SHARED,
        ]);

        Phase::create([
            'project_id' => $matchingProject->id,
            'name' => 'Phase Alpha Visible',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(2),
            'end_date' => Carbon::today()->addDays(15),
        ]);

        Phase::create([
            'project_id' => $filteredOutProject->id,
            'name' => 'Phase Hidden Archive',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(3),
            'end_date' => Carbon::today()->addDays(10),
        ]);

        $response = $this->actingAs($user)->get(route('timeline.index', [
            'project_statuses' => [Project::STATUS_ACTIVE],
            'staffing_models' => [Project::STAFFING_DEDICATED],
        ]));

        $response->assertOk();
        $response->assertSeeText('Phase Alpha Visible');
        $response->assertDontSeeText('Phase Hidden Archive');
    }

    public function test_timeline_filters_by_project_tags(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $tag = Tag::create(['name' => 'Client Work']);

        $taggedProject = Project::create([
            'name' => 'Tagged Timeline Project',
            'description' => 'Tagged project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);
        $taggedProject->tags()->attach($tag->id);

        $untaggedProject = Project::create([
            'name' => 'Untagged Timeline Project',
            'description' => 'Untagged project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        Phase::create([
            'project_id' => $taggedProject->id,
            'name' => 'Tagged Phase Visible',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(1),
            'end_date' => Carbon::today()->addDays(8),
        ]);

        Phase::create([
            'project_id' => $untaggedProject->id,
            'name' => 'Untagged Phase Hidden',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(1),
            'end_date' => Carbon::today()->addDays(12),
        ]);

        $response = $this->actingAs($user)->get(route('timeline.index', [
            'tag_ids' => [$tag->id],
        ]));

        $response->assertOk();
        $response->assertSeeText('Tagged Phase Visible');
        $response->assertDontSeeText('Untagged Phase Hidden');
    }

    public function test_timeline_filters_by_assignee_and_task_status(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $assigneeA = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $assigneeB = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $project = Project::create([
            'name' => 'Assignee Filter Project',
            'description' => 'Timeline assignee filtering',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Backlog',
            'position' => 1,
            'is_default' => true,
        ]);

        $phaseVisible = Phase::create([
            'project_id' => $project->id,
            'name' => 'Assignee Match Phase',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(1),
            'end_date' => Carbon::today()->addDays(7),
        ]);

        $phaseHidden = Phase::create([
            'project_id' => $project->id,
            'name' => 'Assignee Mismatch Phase',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(2),
            'end_date' => Carbon::today()->addDays(10),
        ]);

        $taskA = Task::create([
            'phase_id' => $phaseVisible->id,
            'column_id' => $column->id,
            'title' => 'Task Active A',
            'status' => 'active',
            'position' => 1,
            'due_date' => Carbon::today()->addDays(6),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $taskA->users()->attach($assigneeA->id);

        $taskB = Task::create([
            'phase_id' => $phaseHidden->id,
            'column_id' => $column->id,
            'title' => 'Task Planned B',
            'status' => 'planned',
            'position' => 2,
            'due_date' => Carbon::today()->addDays(8),
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
        $taskB->users()->attach($assigneeB->id);

        $response = $this->actingAs($user)->get(route('timeline.index', [
            'assignee_ids' => [$assigneeA->id],
            'task_statuses' => ['active'],
        ]));

        $response->assertOk();
        $response->assertSeeText('Assignee Match Phase');
        $response->assertDontSeeText('Assignee Mismatch Phase');
    }

    public function test_timeline_overdue_only_filters_to_overdue_work(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $project = Project::create([
            'name' => 'Overdue Filter Project',
            'description' => 'Timeline overdue filtering',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        Phase::create([
            'project_id' => $project->id,
            'name' => 'Phase Overdue Visible',
            'status' => 'active',
            'start_date' => Carbon::today()->subDays(14),
            'end_date' => Carbon::today()->subDay(),
        ]);

        Phase::create([
            'project_id' => $project->id,
            'name' => 'Phase Future Hidden',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(2),
            'end_date' => Carbon::today()->addDays(9),
        ]);

        $response = $this->actingAs($user)->get(route('timeline.index', [
            'overdue_only' => 1,
        ]));

        $response->assertOk();
        $response->assertSeeText('Phase Overdue Visible');
        $response->assertDontSeeText('Phase Future Hidden');
    }

    public function test_timeline_returns_partial_for_htmx_requests(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $project = Project::create([
            'name' => 'HTMX Timeline Project',
            'description' => 'Partial rendering check',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        Phase::create([
            'project_id' => $project->id,
            'name' => 'HTMX Phase Row',
            'status' => 'active',
            'start_date' => Carbon::today()->addDays(1),
            'end_date' => Carbon::today()->addDays(5),
        ]);

        $response = $this->actingAs($user)
            ->withHeaders(['HX-Request' => 'true'])
            ->get(route('timeline.index'));

        $response->assertOk();
        $response->assertSee('id="timeline-page"', false);
        $response->assertDontSee('<!DOCTYPE html>', false);
    }
}
