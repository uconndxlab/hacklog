<?php

namespace Tests\Feature;

use App\Jobs\ProcessSlackEventJob;
use App\Models\Column;
use App\Models\Phase;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\SlackBotService;
use App\Services\SlackIdentityService;
use App\Services\SlackQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackIdentityLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_command_links_verified_slack_sender_to_active_hacklog_user(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1700000000.000002',
            ]),
        ]);

        $user = User::factory()->create([
            'netid' => 'jmk22028',
            'name' => 'Jay Kay',
            'active' => true,
        ]);

        $job = new ProcessSlackEventJob([
            'event' => [
                'type' => 'app_mention',
                'channel' => 'C123456',
                'user' => 'U123456',
                'text' => '<@UBOT123> I am JMK22028',
                'ts' => '1700000000.000001',
            ],
        ], 'Ev123');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        $this->assertSame('U123456', $user->refresh()->slack_id);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $request['channel'] === 'C123456'
                && $request['thread_ts'] === '1700000000.000001'
                && str_contains($request['text'], "You're linked to *Jay Kay*");
        });
    }

    public function test_identity_command_is_global_and_does_not_require_a_project_channel(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response([
                'ok' => true,
                'ts' => '1700000000.000002',
            ]),
        ]);

        $user = User::factory()->create([
            'netid' => 'jmk22028',
            'name' => 'Jay Kay',
            'active' => true,
        ]);

        $this->assertDatabaseMissing('projects', ['slack_channel_id' => 'CUNMAPPED']);

        $job = new ProcessSlackEventJob($this->slackEventPayload([
            'type' => 'app_mention',
            'channel' => 'CUNMAPPED',
            'user' => 'U123456',
            'text' => '<@UBOT123|Hacklog> I am JMK22028',
            'ts' => '1700000000.000001',
        ]), 'Ev129');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        $this->assertSame('U123456', $user->refresh()->slack_id);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://slack.com/api/chat.postMessage'
                && $request['channel'] === 'CUNMAPPED'
                && str_contains($request['text'], "You're linked to *Jay Kay*")
                && ! str_contains($request['text'], "isn't connected")
                && ! str_contains($request['text'], 'not enabled');
        });
    }

    public function test_linking_is_idempotent(): void
    {
        $user = User::factory()->create([
            'netid' => 'jmk22028',
            'active' => true,
        ]);
        $service = new SlackIdentityService;

        $first = $service->link('U123456', 'jmk22028');
        $second = $service->link('U123456', 'jmk22028');

        $this->assertSame(SlackIdentityService::LINKED, $first['status']);
        $this->assertSame(SlackIdentityService::ALREADY_LINKED, $second['status']);
        $this->assertSame('U123456', $user->refresh()->slack_id);
    }

    public function test_my_tasks_uses_the_linked_slack_user(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        $linkedUser = User::factory()->create([
            'netid' => 'jmk22028',
            'slack_id' => 'U123456',
            'active' => true,
        ]);
        $otherUser = User::factory()->create(['active' => true]);
        $project = Project::create([
            'name' => 'Website',
            'status' => Project::STATUS_ACTIVE,
            'slack_channel_id' => 'C123456',
            'slack_bot_enabled' => true,
        ]);
        $otherProject = Project::create([
            'name' => 'API',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $phase = Phase::create(['project_id' => $project->id, 'name' => 'Build']);
        $otherPhase = Phase::create(['project_id' => $otherProject->id, 'name' => 'Build']);
        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Planned',
            'position' => 1,
        ]);
        $otherColumn = Column::create([
            'project_id' => $otherProject->id,
            'name' => 'Planned',
            'position' => 1,
        ]);
        $mine = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'My assigned task',
            'status' => 'planned',
            'position' => 1,
        ]);
        $mine->users()->attach($linkedUser);
        $otherMine = Task::create([
            'phase_id' => $otherPhase->id,
            'column_id' => $otherColumn->id,
            'title' => 'API work',
            'status' => 'planned',
            'position' => 1,
        ]);
        $otherMine->users()->attach($linkedUser);
        $theirs = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Someone else task',
            'status' => 'planned',
            'position' => 2,
        ]);
        $theirs->users()->attach($otherUser);
        $waiting = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Waiting on feedback',
            'status' => 'awaiting_feedback',
            'position' => 3,
        ]);
        $waiting->users()->attach($linkedUser);

        $job = new ProcessSlackEventJob($this->slackEventPayload([
            'type' => 'app_mention',
            'channel' => 'C123456',
            'user' => 'U123456',
            'text' => '<@UBOT123> show me my tasks',
            'ts' => '1700000000.000001',
        ]), 'Ev124');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        Http::assertSent(function ($request): bool {
            return str_contains($request['text'], '*2 open tasks assigned to you*')
                && str_contains($request['text'], '*Website*')
                && str_contains($request['text'], 'My assigned task')
                && str_contains($request['text'], '*API*')
                && str_contains($request['text'], 'API work')
                && ! str_contains($request['text'], 'Someone else task')
                && ! str_contains($request['text'], 'Waiting on feedback');
        });
    }

    public function test_my_tasks_in_this_project_is_limited_to_the_current_channel(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        $linkedUser = User::factory()->create([
            'netid' => 'jmk22028',
            'slack_id' => 'U123456',
            'active' => true,
        ]);
        $project = Project::create([
            'name' => 'Website',
            'status' => Project::STATUS_ACTIVE,
            'slack_channel_id' => 'C123456',
            'slack_bot_enabled' => true,
        ]);
        $otherProject = Project::create([
            'name' => 'API',
            'status' => Project::STATUS_ACTIVE,
        ]);
        $phase = Phase::create(['project_id' => $project->id, 'name' => 'Build']);
        $otherPhase = Phase::create(['project_id' => $otherProject->id, 'name' => 'Build']);
        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Planned',
            'position' => 1,
        ]);
        $otherColumn = Column::create([
            'project_id' => $otherProject->id,
            'name' => 'Planned',
            'position' => 1,
        ]);
        $mine = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Website task',
            'status' => 'planned',
            'position' => 1,
        ]);
        $mine->users()->attach($linkedUser);
        $otherMine = Task::create([
            'phase_id' => $otherPhase->id,
            'column_id' => $otherColumn->id,
            'title' => 'API work',
            'status' => 'planned',
            'position' => 1,
        ]);
        $otherMine->users()->attach($linkedUser);

        $job = new ProcessSlackEventJob($this->slackEventPayload([
            'type' => 'app_mention',
            'channel' => 'C123456',
            'user' => 'U123456',
            'text' => '<@UBOT123> my tasks in this project',
            'ts' => '1700000000.000001',
        ]), 'Ev125');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        Http::assertSent(function ($request): bool {
            return str_contains($request['text'], '*1 open Website task assigned to you*')
                && str_contains($request['text'], 'Website task')
                && ! str_contains($request['text'], 'API work');
        });
    }

    public function test_mentioning_another_linked_slack_user_shows_their_tasks(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        $asker = User::factory()->create([
            'name' => 'Asker',
            'slack_id' => 'UASKER',
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
        $teammate = User::factory()->create([
            'name' => 'Jay Kay',
            'slack_id' => 'UJAY',
            'active' => true,
        ]);
        $project = Project::create([
            'name' => 'Website',
            'status' => Project::STATUS_ACTIVE,
            'slack_channel_id' => 'C123456',
            'slack_bot_enabled' => true,
        ]);
        $phase = Phase::create(['project_id' => $project->id, 'name' => 'Build']);
        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Planned',
            'position' => 1,
        ]);
        $theirs = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Jay website work',
            'status' => 'planned',
            'position' => 1,
        ]);
        $theirs->users()->attach($teammate);
        $mine = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Asker only task',
            'status' => 'planned',
            'position' => 2,
        ]);
        $mine->users()->attach($asker);

        $job = new ProcessSlackEventJob($this->slackEventPayload([
            'type' => 'app_mention',
            'channel' => 'C123456',
            'user' => 'UASKER',
            'text' => "<@UBOT123> what are <@UJAY>'s tasks",
            'ts' => '1700000000.000001',
        ]), 'Ev126');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        Http::assertSent(function ($request): bool {
            return str_contains($request['text'], '*1 open task assigned to Jay Kay*')
                && str_contains($request['text'], 'Jay website work')
                && ! str_contains($request['text'], 'Asker only task');
        });
    }

    public function test_non_admin_cannot_look_up_another_users_tasks(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        User::factory()->create([
            'name' => 'Client Asker',
            'slack_id' => 'UASKER',
            'role' => User::ROLE_CLIENT,
            'active' => true,
        ]);
        $teammate = User::factory()->create([
            'name' => 'Jay Kay',
            'slack_id' => 'UJAY',
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);
        $project = Project::create([
            'name' => 'Website',
            'status' => Project::STATUS_ACTIVE,
            'slack_channel_id' => 'C123456',
            'slack_bot_enabled' => true,
        ]);
        $phase = Phase::create(['project_id' => $project->id, 'name' => 'Build']);
        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Planned',
            'position' => 1,
        ]);
        $theirs = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Secret other-client work',
            'status' => 'planned',
            'position' => 1,
        ]);
        $theirs->users()->attach($teammate);

        $job = new ProcessSlackEventJob($this->slackEventPayload([
            'type' => 'app_mention',
            'channel' => 'C123456',
            'user' => 'UASKER',
            'text' => "<@UBOT123> what are <@UJAY>'s tasks",
            'ts' => '1700000000.000001',
        ]), 'Ev128');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        Http::assertSent(function ($request): bool {
            return str_contains($request['text'], "Only a Hacklog admin can look up another person's tasks")
                && ! str_contains($request['text'], 'Secret other-client work');
        });
    }

    public function test_mentioning_an_unlinked_slack_user_asks_them_to_link(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        User::factory()->create([
            'slack_id' => 'UASKER',
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
        $project = Project::create([
            'name' => 'Website',
            'status' => Project::STATUS_ACTIVE,
            'slack_channel_id' => 'C123456',
            'slack_bot_enabled' => true,
        ]);

        $job = new ProcessSlackEventJob($this->slackEventPayload([
            'type' => 'app_mention',
            'channel' => 'C123456',
            'user' => 'UASKER',
            'text' => "what are <@USTRANGER>'s tasks",
            'ts' => '1700000000.000001',
        ]), 'Ev127');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        Http::assertSent(function ($request): bool {
            return str_contains($request['text'], "I don't have a Hacklog account linked to that Slack user yet");
        });
    }

    public function test_multiple_other_mentions_ask_for_a_single_teammate(): void
    {
        config(['slack.bot_token' => 'test-token']);
        Http::fake([
            'https://slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        User::factory()->create([
            'name' => 'Asker',
            'slack_id' => 'UASKER',
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);
        $teammate = User::factory()->create([
            'name' => 'Jay Kay',
            'slack_id' => 'UJAY',
            'active' => true,
        ]);
        $project = Project::create([
            'name' => 'Website',
            'status' => Project::STATUS_ACTIVE,
            'slack_channel_id' => 'C123456',
            'slack_bot_enabled' => true,
        ]);
        $phase = Phase::create(['project_id' => $project->id, 'name' => 'Build']);
        $column = Column::create([
            'project_id' => $project->id,
            'name' => 'Planned',
            'position' => 1,
        ]);
        $theirs = Task::create([
            'phase_id' => $phase->id,
            'column_id' => $column->id,
            'title' => 'Jay website work',
            'status' => 'planned',
            'position' => 1,
        ]);
        $theirs->users()->attach($teammate);

        $job = new ProcessSlackEventJob($this->slackEventPayload([
            'type' => 'app_mention',
            'channel' => 'C123456',
            'user' => 'UASKER',
            'text' => "<@UBOT123> what are <@UJAY> and <@USTRANGER>'s tasks",
            'ts' => '1700000000.000001',
        ]), 'Ev130');

        $job->handle(new SlackBotService, new SlackQueryService, new SlackIdentityService);

        Http::assertSent(function ($request): bool {
            return str_contains($request['text'], 'Mention a single teammate so I know whose tasks to show')
                && ! str_contains($request['text'], 'Jay website work');
        });
    }

    public function test_linking_cannot_take_over_either_side_of_an_existing_link(): void
    {
        $firstUser = User::factory()->create([
            'netid' => 'abc12345',
            'slack_id' => 'U-FIRST',
            'active' => true,
        ]);
        $secondUser = User::factory()->create([
            'netid' => 'def12345',
            'active' => true,
        ]);
        $service = new SlackIdentityService;

        $slackConflict = $service->link('U-FIRST', 'def12345');
        $userConflict = $service->link('U-SECOND', 'abc12345');

        $this->assertSame(SlackIdentityService::SLACK_ALREADY_LINKED, $slackConflict['status']);
        $this->assertSame(SlackIdentityService::USER_ALREADY_LINKED, $userConflict['status']);
        $this->assertSame('U-FIRST', $firstUser->refresh()->slack_id);
        $this->assertNull($secondUser->refresh()->slack_id);
    }

    public function test_inactive_or_unknown_user_cannot_be_linked(): void
    {
        User::factory()->create([
            'netid' => 'off12345',
            'active' => false,
        ]);
        $service = new SlackIdentityService;

        $inactive = $service->link('U-INACTIVE', 'off12345');
        $unknown = $service->link('U-UNKNOWN', 'missing1');

        $this->assertSame(SlackIdentityService::USER_NOT_FOUND, $inactive['status']);
        $this->assertSame(SlackIdentityService::USER_NOT_FOUND, $unknown['status']);
        $this->assertDatabaseMissing('users', ['slack_id' => 'U-INACTIVE']);
        $this->assertDatabaseMissing('users', ['slack_id' => 'U-UNKNOWN']);
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function slackEventPayload(array $event): array
    {
        return [
            'authorizations' => [
                ['user_id' => 'UBOT123', 'is_bot' => true],
            ],
            'event' => $event,
        ];
    }
}
