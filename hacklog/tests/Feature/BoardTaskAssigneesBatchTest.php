<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTaskAssigneesBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_selection_adds_assignees_without_removing_existing(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $alice = User::factory()->create(['name' => 'Alice', 'active' => true]);
        $bob = User::factory()->create(['name' => 'Bob', 'active' => true]);
        $carol = User::factory()->create(['name' => 'Carol', 'active' => true]);

        $project = Project::create(['name' => 'Board']);
        $column = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $a = $column->tasks()->create(['title' => 'A', 'position' => 0]);
        $b = $column->tasks()->create(['title' => 'B', 'position' => 1]);
        $c = $column->tasks()->create(['title' => 'C', 'position' => 2]);
        $a->users()->attach($alice->id);

        $response = $this->actingAs($user)->postJson(route('projects.board.tasks.assignees-batch', $project), [
            'task_ids' => [$a->id, $b->id],
            'assignee_ids' => [$bob->id, $carol->id],
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEqualsCanonicalizing([$alice->id, $bob->id, $carol->id], $a->fresh()->users->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$bob->id, $carol->id], $b->fresh()->users->pluck('id')->all());
        $this->assertSame([], $c->fresh()->users->pluck('id')->all());
        $this->assertSame($user->id, $a->fresh()->updated_by);
        $this->assertDatabaseHas('task_activities', ['task_id' => $a->id, 'action' => 'assignees_changed']);
        $this->assertDatabaseHas('task_activities', ['task_id' => $b->id, 'action' => 'assignees_changed']);

        $taskPayload = collect($response->json('tasks'))->keyBy('id');
        $this->assertEqualsCanonicalizing(
            [$alice->id, $bob->id, $carol->id],
            collect($taskPayload[$a->id]['assignees'])->pluck('id')->all()
        );
    }

    public function test_already_assigned_users_are_skipped_without_activity(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $alice = User::factory()->create(['active' => true]);
        $project = Project::create(['name' => 'Board']);
        $column = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $task = $column->tasks()->create(['title' => 'Already', 'position' => 0]);
        $task->users()->attach($alice->id);

        $this->actingAs($user)->postJson(route('projects.board.tasks.assignees-batch', $project), [
            'task_ids' => [$task->id],
            'assignee_ids' => [$alice->id],
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertEqualsCanonicalizing([$alice->id], $task->fresh()->users->pluck('id')->all());
        $this->assertDatabaseMissing('task_activities', ['task_id' => $task->id, 'action' => 'assignees_changed']);
    }

    public function test_clients_and_invalid_payloads_are_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'active' => true]);
        $client = User::factory()->create(['role' => User::ROLE_CLIENT, 'active' => true]);
        $assignee = User::factory()->create(['active' => true]);
        $project = Project::create(['name' => 'Board']);
        $column = $project->columns()->create(['name' => 'Backlog', 'position' => 0]);
        $task = $column->tasks()->create(['title' => 'Local', 'position' => 0]);
        $other = Project::create(['name' => 'Other'])->columns()->create(['name' => 'Other', 'position' => 0]);
        $foreign = $other->tasks()->create(['title' => 'Foreign', 'position' => 0]);

        $this->actingAs($client)->postJson(route('projects.board.tasks.assignees-batch', $project), [
            'task_ids' => [$task->id],
            'assignee_ids' => [$assignee->id],
        ])->assertForbidden();

        foreach ([
            ['task_ids' => [$task->id, $foreign->id], 'assignee_ids' => [$assignee->id]],
            ['task_ids' => [$task->id, $task->id], 'assignee_ids' => [$assignee->id]],
            ['task_ids' => [$task->id], 'assignee_ids' => []],
        ] as $payload) {
            $this->actingAs($admin)->postJson(route('projects.board.tasks.assignees-batch', $project), $payload)->assertUnprocessable();
            $this->assertSame([], $task->fresh()->users->pluck('id')->all());
        }
    }
}
