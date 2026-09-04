<?php

namespace Tests\Feature;

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProjectBoardCompletedFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Column $column;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-04 12:00:00');

        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->project = Project::create([
            'name' => 'Completed filter project',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $this->column = Column::create([
            'project_id' => $this->project->id,
            'name' => 'Done',
            'position' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_board_hides_tasks_completed_more_than_30_days_ago_by_default(): void
    {
        $this->createTask('Open task', 'active', null, now()->subDays(100));
        $this->createTask('Recently completed task', 'completed', now()->subDays(29));
        $this->createTask('Old completed task', 'completed', now()->subDays(31));
        $this->createTask('Old legacy completed task', 'completed', null, now()->subDays(31));

        $response = $this->actingAs($this->user)
            ->get(route('projects.board', $this->project));

        $response->assertOk()
            ->assertSee('Open task')
            ->assertSee('Recently completed task')
            ->assertDontSee('Old completed task')
            ->assertDontSee('Old legacy completed task')
            ->assertSee('value="30"', false);
    }

    public function test_board_accepts_a_custom_completed_task_age(): void
    {
        $this->createTask('Completed 45 days ago', 'completed', now()->subDays(45));
        $this->createTask('Completed 75 days ago', 'completed', now()->subDays(75));

        $response = $this->actingAs($this->user)
            ->get(route('projects.board', [
                'project' => $this->project,
                'completed_days' => 60,
            ]));

        $response->assertOk()
            ->assertSee('Completed 45 days ago')
            ->assertDontSee('Completed 75 days ago');
    }

    public function test_board_can_show_all_completed_tasks(): void
    {
        $this->createTask('Very old completed task', 'completed', now()->subYears(2));

        $response = $this->actingAs($this->user)
            ->get(route('projects.board', [
                'project' => $this->project,
                'completed_days' => 'all',
            ]));

        $response->assertOk()
            ->assertSee('Very old completed task')
            ->assertSee('Show all completed')
            ->assertSee('value="30"', false);
    }

    private function createTask(
        string $title,
        string $status,
        ?Carbon $completedAt,
        ?Carbon $updatedAt = null,
    ): Task {
        $task = Task::create([
            'column_id' => $this->column->id,
            'title' => $title,
            'status' => $status,
            'position' => 0,
            'completed_at' => $completedAt,
            'created_by' => $this->user->id,
        ]);

        if ($updatedAt) {
            $task->timestamps = false;
            $task->updated_at = $updatedAt;
            $task->save();
        }

        return $task;
    }
}
