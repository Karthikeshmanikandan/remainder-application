<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_login_layout(): void
    {
        $response = $this->get(route('login'));

        $response->assertSuccessful();
        $response->assertSee('Sign in to your account');
        $response->assertDontSee('id="sidebar"', false);
    }

    public function test_authenticated_admin_sees_all_navigation_groups_and_admin_links(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('Workspace');
        $response->assertSee('Dashboard');
        $response->assertSee('Projects');
        $response->assertSee('Tasks');
        $response->assertSee('Recurring Tasks');
        $response->assertSee('Reminders');
        $response->assertSee('Notifications');
        $response->assertSee('Telegram Bot');
        $response->assertSee('Administration');
        $response->assertSee('User Management');
        $response->assertSee('Telegram Status');
    }

    public function test_authenticated_employee_cannot_see_administration_navigation_links(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);

        $response = $this->actingAs($employee)->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('Dashboard');
        $response->assertSee('Tasks');
        $response->assertSee('Notifications');
        $response->assertSee('Telegram Bot');

        // Admin-only links are NOT visible in sidebar for employee
        $response->assertDontSee('User Management');
        $response->assertDontSee('Telegram Status');
    }

    public function test_authenticated_manager_sees_management_navigation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertSuccessful();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('Dashboard');
        $response->assertSee('Projects');
        $response->assertSee('Tasks');
        $response->assertSee('Recurring Tasks');
        $response->assertSee('Telegram Bot');

        // Manager does not see admin user management
        $response->assertDontSee('User Management');
    }
}
