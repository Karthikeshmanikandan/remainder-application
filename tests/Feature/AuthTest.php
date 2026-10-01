<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login()
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/projects')->assertRedirect('/login');
        $this->get('/tasks')->assertRedirect('/login');
    }

    public function test_user_can_login()
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!'), 'status' => UserStatus::ACTIVE]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);
        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login()
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!'), 'status' => UserStatus::INACTIVE]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ]);
        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_invalid_login()
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ]);
        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }
}
