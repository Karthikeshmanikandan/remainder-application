<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_can_be_created()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/projects', [
            'code' => 'PRJ-100',
            'name' => 'New Project',
            'status' => 'active',
        ]);
        $response->assertRedirect('/projects');
        $this->assertDatabaseHas('projects', ['code' => 'PRJ-100']);
    }

    public function test_invalid_project_data_is_rejected()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/projects', [
            'code' => '',
            'name' => 'New Project',
            'status' => 'invalid',
        ]);
        $response->assertSessionHasErrors(['code', 'status']);
    }

    public function test_project_can_be_updated()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $response = $this->actingAs($user)->put("/projects/{$project->id}", [
            'code' => $project->code,
            'name' => 'Updated Name',
            'status' => 'completed',
        ]);
        $response->assertRedirect('/projects');
        $this->assertEquals('Updated Name', $project->fresh()->name);
    }

    public function test_project_can_be_viewed()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $response = $this->actingAs($user)->get("/projects/{$project->id}");
        $response->assertStatus(200);
        $response->assertSee($project->name);
    }

    public function test_project_can_be_deleted()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $response = $this->actingAs($user)->delete("/projects/{$project->id}");
        $response->assertRedirect('/projects');
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_project_progress_is_calculated_correctly()
    {
        $project = Project::factory()->create();
        $this->assertEquals(0, $project->progress()['percentage']);
    }
}
