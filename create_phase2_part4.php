<?php

function create_file($path, $content)
{
    $dir = dirname(__DIR__.'/'.$path);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents(__DIR__.'/'.$path, $content);
    echo "Created: $path\n";
}

create_file('tests/Feature/DashboardTest.php', <<<'PHP'
<?php
namespace Tests\Feature;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_and_shows_statistics()
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->create();
        Task::factory()->count(3)->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewHasAll(['totalProjects', 'totalTasks']);
    }
}
PHP);

create_file('tests/Feature/ProjectTest.php', <<<'PHP'
<?php
namespace Tests\Feature;
use App\Models\Project;
use App\Models\User;
use App\Enums\ProjectStatus;
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
PHP);

create_file('tests/Feature/TaskTest.php', <<<'PHP'
<?php
namespace Tests\Feature;
use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Enums\TaskStatus;
use App\Enums\TaskPriority;
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
PHP);
