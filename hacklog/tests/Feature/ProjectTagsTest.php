<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_user_can_create_project_with_existing_and_new_tags(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $existingTag = Tag::create(['name' => 'Backend']);

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Taggable Project',
            'description' => 'Project with tags',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
            'use_default_columns' => false,
            'tags_sync' => 1,
            'tags' => [$existingTag->id],
            'new_tags' => 'Security, Frontend',
        ]);

        $response->assertRedirect();

        $project = Project::where('name', 'Taggable Project')->firstOrFail();
        $tagNames = $project->tags()->orderBy('name')->pluck('name')->all();

        $this->assertEquals(['Backend', 'Frontend', 'Security'], $tagNames);
        $this->assertDatabaseHas('project_tag', [
            'project_id' => $project->id,
            'tag_id' => $existingTag->id,
        ]);
    }

    public function test_projects_index_can_filter_by_tag(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_TEAM,
            'active' => true,
        ]);

        $tag = Tag::create(['name' => 'Research']);

        $taggedProject = Project::create([
            'name' => 'Tagged Project',
            'description' => 'Has the selected tag',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);
        $taggedProject->tags()->attach($tag->id);

        Project::create([
            'name' => 'Other Project',
            'description' => 'No matching tag',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);

        $response = $this->actingAs($user)->get(route('projects.index', ['tag' => $tag->id]));

        $response->assertOk();
        $response->assertSee('Tagged Project');
        $response->assertDontSee('Other Project');
    }

    public function test_client_cannot_modify_project_tags(): void
    {
        $client = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'active' => true,
        ]);

        $tag = Tag::create(['name' => 'Platform']);

        $project = Project::create([
            'name' => 'Client Locked Project',
            'description' => 'Should reject client tag edits',
            'status' => Project::STATUS_ACTIVE,
            'staffing_model' => Project::STAFFING_DEDICATED,
        ]);
        $project->tags()->attach($tag->id);

        $response = $this->actingAs($client)->put(route('projects.update', $project), [
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'staffing_model' => $project->staffing_model,
            'tags_sync' => 1,
            'tags' => [],
            'new_tags' => 'Secret',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('project_tag', [
            'project_id' => $project->id,
            'tag_id' => $tag->id,
        ]);
        $this->assertDatabaseMissing('tags', ['name' => 'Secret']);
    }
}
