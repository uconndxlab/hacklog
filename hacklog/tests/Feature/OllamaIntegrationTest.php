<?php

namespace Tests\Feature;

use App\Models\Column;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_user_can_view_ollama_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('ollama.index'));

        $response->assertOk();
        $response->assertSee('AI Test');
    }

    public function test_admin_user_can_view_ollama_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('ollama.index'));

        $response->assertOk();
    }

    public function test_client_user_cannot_view_ollama_page(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('ollama.index'));

        $response->assertForbidden();
    }

    public function test_valid_prompt_is_sent_to_ollama_and_response_is_displayed(): void
    {
        config()->set('ollama.base_url', 'http://127.0.0.1:11434');
        config()->set('ollama.model', 'gemma4');
        config()->set('ollama.chat_path', '/api/chat');

        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::sequence()
                ->push([
                    'message' => [
                        'role' => 'assistant',
                        'content' => '',
                        'tool_calls' => [
                            [
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'count_open_tasks_by_project',
                                    'arguments' => ['limit' => 3],
                                ],
                            ],
                        ],
                    ],
                    'done' => true,
                ], 200)
                ->push([
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Atlas has the most open tasks with 2 open tasks.',
                    ],
                    'done' => true,
                ], 200),
        ]);

        $projectA = Project::create([
            'name' => 'Atlas',
            'description' => 'Atlas project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $projectB = Project::create([
            'name' => 'Nexus',
            'description' => 'Nexus project',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $columnA = Column::create([
            'project_id' => $projectA->id,
            'name' => 'Backlog',
            'position' => 1,
            'is_default' => true,
        ]);

        $columnB = Column::create([
            'project_id' => $projectB->id,
            'name' => 'Backlog',
            'position' => 1,
            'is_default' => true,
        ]);

        Task::create([
            'phase_id' => null,
            'column_id' => $columnA->id,
            'title' => 'Atlas Task 1',
            'description' => null,
            'status' => 'planned',
            'position' => 1,
            'created_by' => null,
            'updated_by' => null,
        ]);

        Task::create([
            'phase_id' => null,
            'column_id' => $columnA->id,
            'title' => 'Atlas Task 2',
            'description' => null,
            'status' => 'active',
            'position' => 2,
            'created_by' => null,
            'updated_by' => null,
        ]);

        Task::create([
            'phase_id' => null,
            'column_id' => $columnB->id,
            'title' => 'Nexus Task 1',
            'description' => null,
            'status' => 'active',
            'position' => 1,
            'created_by' => null,
            'updated_by' => null,
        ]);

        Task::create([
            'phase_id' => null,
            'column_id' => $columnB->id,
            'title' => 'Nexus Done',
            'description' => null,
            'status' => 'completed',
            'position' => 2,
            'created_by' => null,
            'updated_by' => null,
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('ollama.store'), [
            'prompt' => 'Which project has the most open tasks?',
        ]);

        $response->assertOk();
        $response->assertSee('Atlas has the most open tasks with 2 open tasks.');

        Http::assertSentCount(2);

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->url() === 'http://127.0.0.1:11434/api/chat'
                && ($data['model'] ?? null) === 'gemma4'
                && ($data['stream'] ?? null) === false
                && is_array($data['tools'] ?? null);
        });
    }

    public function test_prompt_is_required(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('ollama.store'), [
            'prompt' => '',
        ]);

        $response->assertSessionHasErrors('prompt');
    }

    public function test_ollama_failure_redirects_with_error_message(): void
    {
        config()->set('ollama.base_url', 'http://127.0.0.1:11434');
        config()->set('ollama.model', 'gemma4');
        config()->set('ollama.chat_path', '/api/chat');

        Http::fake([
            'http://127.0.0.1:11434/api/chat' => Http::response([
                'error' => 'model not found',
            ], 500),
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $response = $this->from(route('ollama.index'))->actingAs($user)->post(route('ollama.store'), [
            'prompt' => 'hello',
        ]);

        $response->assertRedirect(route('ollama.index'));
        $response->assertSessionHas('error', 'Ollama error: model not found');
    }
}
