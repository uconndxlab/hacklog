<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectSettingsSlackWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_user_can_create_project_with_slack_webhook_url(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $webhookUrl = 'https://hooks.slack.com/services/T000/B000/abc123';

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Slack Enabled Project',
            'description' => 'Project with Slack notifications',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'slack_webhook_url' => $webhookUrl,
            'use_default_columns' => false,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'name' => 'Slack Enabled Project',
            'slack_webhook_url' => $webhookUrl,
        ]);
    }

    public function test_team_user_can_clear_slack_webhook_url_when_updating_project(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $project = Project::create([
            'name' => 'Clear Slack URL',
            'description' => 'Before clearing',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'slack_webhook_url' => 'https://hooks.slack.com/services/T000/B000/clearme',
        ]);

        $response = $this->actingAs($user)->put(route('projects.update', $project), [
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'staffing_model' => $project->staffing_model,
            'slack_webhook_url' => '   ',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'slack_webhook_url' => null,
        ]);
    }

    public function test_invalid_slack_webhook_url_is_rejected(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Invalid URL Project',
            'description' => 'Project with bad Slack URL',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'slack_webhook_url' => 'not-a-url',
            'use_default_columns' => false,
        ]);

        $response->assertSessionHasErrors('slack_webhook_url');
    }
}
