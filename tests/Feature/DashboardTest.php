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

    public function test_dashboard_loads_and_shows_statistics(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->create();
        Task::factory()->count(3)->create();

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertViewHasAll([
            'totalProjects',
            'totalTasks',
            'upcomingReminders',
            'recentNotifications',
        ]);
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
