<?php

namespace Tests\Feature;

use App\Jobs\SendProjectSlackTaskSummaryJob;
use App\Models\Column;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ProjectSlackNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProjectSlackNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_task_creation_is_throttled_to_single_scheduled_job_per_project(): void
    {
        Queue::fake();

        [$user, $project, $phase, $column] = $this->makeProjectContext('https://hooks.slack.com/services/T1/B1/board');

        $payload = [
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Board task',
            'description' => 'Created from board modal',
            'status' => 'planned',
        ];

        $this->actingAs($user)->post(route('projects.board.tasks.store', $project), $payload);
        $this->actingAs($user)->post(route('projects.board.tasks.store', $project), array_merge($payload, [
            'title' => 'Board task 2',
        ]));

        Queue::assertPushed(SendProjectSlackTaskSummaryJob::class, 1);
    }

    public function test_all_task_creation_paths_enqueue_summary_job_when_webhook_is_set(): void
    {
        Queue::fake();

        [$user, $project, $phase, $column] = $this->makeProjectContext('https://hooks.slack.com/services/T2/B2/allpaths');

        $this->actingAs($user)->post(route('projects.phases.tasks.store', [$project, $phase]), [
            'column_id' => $column->id,
            'title' => 'Phase route task',
            'description' => 'Created via phase route',
            'status' => 'planned',
        ]);

        $this->actingAs($user)->post(route('projects.board.tasks.store', $project), [
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Board route task',
            'description' => 'Created via board route',
            'status' => 'planned',
        ]);

        $this->actingAs($user)->post(route('projects.tasks.store', $project), [
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Project route task',
            'description' => 'Created via project route',
            'status' => 'planned',
        ]);

        Queue::assertPushed(SendProjectSlackTaskSummaryJob::class, 1);
    }

    public function test_task_creation_does_not_enqueue_when_project_has_no_webhook(): void
    {
        Queue::fake();

        [$user, $project, $phase, $column] = $this->makeProjectContext(null);

        $this->actingAs($user)->post(route('projects.board.tasks.store', $project), [
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'No webhook task',
            'description' => 'No Slack should be sent',
            'status' => 'planned',
        ]);

        Queue::assertNotPushed(SendProjectSlackTaskSummaryJob::class);
    }

    public function test_task_update_endpoint_enqueues_summary_job_when_webhook_is_set(): void
    {
        Queue::fake();

        [$user, $project, $phase, $column] = $this->makeProjectContext('https://hooks.slack.com/services/T9/B9/update');

        $task = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Needs update',
            'description' => 'Before update',
            'status' => 'planned',
            'position' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $this->actingAs($user)->put(route('projects.board.tasks.update', [$project, $task]), [
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Needs update',
            'description' => 'After update',
            'status' => 'active',
            'priority' => null,
            'weight' => null,
            'start_date' => null,
            'due_date' => null,
            'assignees' => [],
        ]);

        Queue::assertPushed(SendProjectSlackTaskSummaryJob::class, 1);
    }

    public function test_updates_only_flush_sends_compact_update_count_message(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        [$user, $project, $phase, $column] = $this->makeProjectContext('https://hooks.slack.com/services/T4/B4/updatesonly');

        $taskA = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Task One',
            'description' => 'First task',
            'status' => 'planned',
            'position' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $taskB = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Task Two',
            'description' => 'Second task',
            'status' => 'planned',
            'position' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $service = app(ProjectSlackNotificationService::class);
        $service->queueTaskUpdated($taskA);
        $service->queueTaskUpdated($taskB);
        $service->queueTaskUpdated($taskA);
        $service->flushProjectSummary($project->id);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $text = (string) (($request->data())['text'] ?? '');

            return str_contains($text, '2 task(s) received updates')
                && !str_contains($text, 'new task(s)')
                && str_contains($text, 'Open project board');
        });
    }

    public function test_flush_sends_single_summary_message_with_aggregated_tasks(): void
    {
        Http::fake([
            'https://hooks.slack.com/*' => Http::response('ok', 200),
        ]);

        [$user, $project, $phase, $column] = $this->makeProjectContext('https://hooks.slack.com/services/T3/B3/flush');

        $taskA = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Task Alpha',
            'description' => 'First task',
            'status' => 'planned',
            'position' => 0,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $taskB = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Task Beta',
            'description' => 'Second task',
            'status' => 'planned',
            'position' => 1,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $service = app(ProjectSlackNotificationService::class);
        $service->queueTaskCreated($taskA);
        $service->queueTaskCreated($taskB);
        $service->queueTaskUpdated($taskA);
        $service->queueTaskUpdated($taskB);
        $service->flushProjectSummary($project->id);
        $service->flushProjectSummary($project->id);

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($project): bool {
            $payload = $request->data();
            $text = (string) ($payload['text'] ?? '');

            return $request->url() === $project->slack_webhook_url
                && str_contains($text, $project->name)
                && str_contains($text, 'Task Alpha')
                && str_contains($text, 'Task Beta')
                && str_contains($text, 'and 2 task(s) received updates')
                && str_contains($text, 'Open project board');
        });
    }

    /**
     * @return array{0: User, 1: Project, 2: Phase, 3: Column}
     */
    protected function makeProjectContext(?string $webhookUrl): array
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $project = Project::create([
            'name' => 'Slack Project ' . uniqid(),
            'description' => 'Project context for Slack tests',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'slack_webhook_url' => $webhookUrl,
        ]);

        $phase = Phase::create([
            'project_id' => $project->id,
            'name' => 'Phase 1',
            'description' => 'Main phase',
            'status' => 'active',
        ]);

        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Backlog',
            'position' => 1,
            'is_default' => true,
        ]);

        return [$user, $project, $phase, $column];
    }
}
