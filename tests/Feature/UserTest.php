<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_manage_users()
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'manager',
            'status' => 'active',
        ]);
        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
    }

    public function test_non_admin_cannot_manage_users()
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $this->actingAs($manager)->get('/users')->assertStatus(403);
        $this->actingAs($manager)->post('/users', [])->assertStatus(403);
    }
}
