<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTaskStatusBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_selection_updates_status_completed_at_and_logs_changes(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $project = Project::create(['name' => 'Board']);
        $column = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $planned = $column->tasks()->create(['title' => 'Planned', 'status' => 'planned', 'position' => 0]);
        $active = $column->tasks()->create(['title' => 'Active', 'status' => 'active', 'position' => 1]);
        $completed = $column->tasks()->create(['title' => 'Done', 'status' => 'completed', 'completed_at' => now(), 'position' => 2]);
        $untouched = $column->tasks()->create(['title' => 'Leave me', 'status' => 'planned', 'position' => 3]);

        $this->actingAs($user)->postJson(route('projects.board.tasks.status-batch', $project), [
            'task_ids' => [$planned->id, $active->id, $completed->id],
            'status' => 'completed',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame('completed', $planned->fresh()->status);
        $this->assertNotNull($planned->fresh()->completed_at);
        $this->assertSame('completed', $active->fresh()->status);
        $this->assertNotNull($active->fresh()->completed_at);
        $this->assertSame('completed', $completed->fresh()->status);
        $this->assertSame('planned', $untouched->fresh()->status);
        $this->assertSame($user->id, $planned->fresh()->updated_by);
        $this->assertDatabaseHas('task_activities', ['task_id' => $planned->id, 'action' => 'completed']);
        $this->assertDatabaseHas('task_activities', ['task_id' => $active->id, 'action' => 'completed']);
        $this->assertDatabaseMissing('task_activities', ['task_id' => $completed->id, 'action' => 'completed']);
    }

    public function test_reopening_clears_completed_at_and_logs_reopened(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $project = Project::create(['name' => 'Board']);
        $column = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $a = $column->tasks()->create(['title' => 'A', 'status' => 'completed', 'completed_at' => now(), 'position' => 0]);
        $b = $column->tasks()->create(['title' => 'B', 'status' => 'completed', 'completed_at' => now(), 'position' => 1]);

        $this->actingAs($user)->postJson(route('projects.board.tasks.status-batch', $project), [
            'task_ids' => [$a->id, $b->id],
            'status' => 'active',
        ])->assertOk();

        $this->assertSame('active', $a->fresh()->status);
        $this->assertNull($a->fresh()->completed_at);
        $this->assertSame('active', $b->fresh()->status);
        $this->assertNull($b->fresh()->completed_at);
        $this->assertDatabaseHas('task_activities', ['task_id' => $a->id, 'action' => 'reopened']);
        $this->assertDatabaseHas('task_activities', ['task_id' => $b->id, 'action' => 'reopened']);
    }

    public function test_invalid_selection_or_status_leaves_all_tasks_unchanged(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $project = Project::create(['name' => 'Board']);
        $column = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $task = $column->tasks()->create(['title' => 'Local', 'status' => 'planned', 'position' => 0]);
        $other = Project::create(['name' => 'Other'])->columns()->create(['name' => 'Other', 'position' => 0]);
        $foreign = $other->tasks()->create(['title' => 'Foreign', 'status' => 'planned', 'position' => 0]);

        foreach ([
            ['task_ids' => [$task->id, $foreign->id], 'status' => 'active'],
            ['task_ids' => [$task->id, $task->id], 'status' => 'active'],
            ['task_ids' => [$task->id], 'status' => 'not-a-status'],
        ] as $payload) {
            $this->actingAs($user)->postJson(route('projects.board.tasks.status-batch', $project), $payload)->assertUnprocessable();
            $this->assertSame('planned', $task->fresh()->status);
            $this->assertSame('planned', $foreign->fresh()->status);
        }
    }
}
