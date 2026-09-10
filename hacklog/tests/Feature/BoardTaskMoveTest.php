<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTaskMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_selection_moves_from_multiple_columns_in_order_and_logs_changes(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $project = Project::create(['name' => 'Board']);
        $source = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $target = $project->columns()->create(['name' => 'Doing', 'position' => 1]);
        $a = $source->tasks()->create(['title' => 'A', 'position' => 0]);
        $b = $source->tasks()->create(['title' => 'B', 'position' => 1]);
        $c = $source->tasks()->create(['title' => 'C', 'position' => 2]);
        $anchor = $target->tasks()->create(['title' => 'Visible anchor', 'position' => 2]);
        $d = $target->tasks()->create(['title' => 'D', 'position' => 1]);
        $hidden = $target->tasks()->create(['title' => 'Hidden by filter', 'position' => 0]);

        $this->actingAs($user)->postJson(route('projects.board.tasks.move-batch', $project), [
            'task_ids' => [$a->id, $c->id, $d->id],
            'column_id' => $target->id,
            'before_task_id' => $anchor->id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame([$hidden->id, $a->id, $c->id, $d->id, $anchor->id], $target->tasks()->pluck('id')->all());
        $this->assertSame([0, 1, 2, 3, 4], $target->tasks()->pluck('position')->all());
        $this->assertSame([$b->id], $source->tasks()->pluck('id')->all());
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame($user->id, $a->fresh()->updated_by);
        $this->assertDatabaseHas('task_activities', ['task_id' => $a->id, 'action' => 'column_changed']);
    }

    public function test_invalid_selection_or_destination_leaves_all_tasks_unchanged(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $project = Project::create(['name' => 'Board']);
        $source = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $target = $project->columns()->create(['name' => 'Doing', 'position' => 1]);
        $task = $source->tasks()->create(['title' => 'Local', 'position' => 0]);
        $other = Project::create(['name' => 'Other'])->columns()->create(['name' => 'Other', 'position' => 0]);
        $foreign = $other->tasks()->create(['title' => 'Foreign', 'position' => 0]);

        foreach ([
            ['task_ids' => [$task->id, $foreign->id], 'column_id' => $target->id],
            ['task_ids' => [$task->id], 'column_id' => $other->id],
            ['task_ids' => [$task->id], 'column_id' => $target->id, 'before_task_id' => $foreign->id],
            ['task_ids' => [$task->id, $task->id], 'column_id' => $target->id],
        ] as $payload) {
            $this->actingAs($user)->postJson(route('projects.board.tasks.move-batch', $project), $payload)->assertUnprocessable();
            $this->assertSame($source->id, $task->fresh()->column_id);
            $this->assertSame($other->id, $foreign->fresh()->column_id);
        }
    }

    public function test_selection_can_reorder_within_a_column_and_append_to_empty_column(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $project = Project::create(['name' => 'Board']);
        $source = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $target = $project->columns()->create(['name' => 'Doing', 'position' => 1]);
        $a = $source->tasks()->create(['title' => 'A', 'position' => 0]);
        $b = $source->tasks()->create(['title' => 'B', 'position' => 1]);
        $c = $source->tasks()->create(['title' => 'C', 'position' => 2]);
        $this->actingAs($user)->postJson(route('projects.board.tasks.move-batch', $project), [
            'task_ids' => [$b->id, $c->id], 'column_id' => $source->id, 'before_task_id' => $a->id,
        ])->assertOk();
        $this->assertSame([$b->id, $c->id, $a->id], $source->tasks()->pluck('id')->all());
        $this->actingAs($user)->postJson(route('projects.board.tasks.move-batch', $project), [
            'task_ids' => [$b->id, $c->id, $a->id], 'column_id' => $target->id,
        ])->assertOk();
        $this->assertSame(0, $source->tasks()->count());
        $this->assertSame([$b->id, $c->id, $a->id], $target->tasks()->pluck('id')->all());
        $this->assertSame([0, 1, 2], $target->tasks()->pluck('position')->all());
    }
}
