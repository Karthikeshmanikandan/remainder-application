<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_can_be_created()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $response = $this->actingAs($user)->post('/tasks', [
            'code' => 'TSK-100',
            'title' => 'New Task',
            'project_id' => $project->id,
            'status' => 'pending',
            'priority' => 'low',
        ]);
        $response->assertRedirect('/tasks');
        $this->assertDatabaseHas('tasks', ['code' => 'TSK-100']);
    }

    public function test_invalid_task_data_is_rejected()
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post('/tasks', [
            'code' => 'TSK-100',
            'status' => 'invalid_status',
        ]);
        $response->assertSessionHasErrors(['title', 'project_id', 'status', 'priority']);
    }

    public function test_task_can_be_viewed_updated_deleted()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create();
        $this->actingAs($user)->get("/tasks/{$task->id}")->assertStatus(200)->assertSee($task->title);

        $this->actingAs($user)->put("/tasks/{$task->id}", [
            'code' => $task->code,
            'title' => 'Updated Title',
            'project_id' => $task->project_id,
            'status' => 'completed',
            'priority' => 'urgent',
        ])->assertRedirect('/tasks');
        $this->assertEquals('Updated Title', $task->fresh()->title);

        $this->actingAs($user)->delete("/tasks/{$task->id}")->assertRedirect('/tasks');
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_overdue_task_detection_works()
    {
        $task = Task::factory()->create(['due_date' => now()->subDay(), 'status' => 'pending']);
        $this->assertTrue($task->isOverdue());

        $taskCompleted = Task::factory()->create(['due_date' => now()->subDay(), 'status' => 'completed']);
        $this->assertFalse($taskCompleted->isOverdue());
    }
}
