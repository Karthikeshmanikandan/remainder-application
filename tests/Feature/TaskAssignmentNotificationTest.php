<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\Task;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_task_with_assignee_sends_in_app_and_telegram_notification(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);

        Http::fake([
            'api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 12345],
            ], 200),
        ]);

        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create();

        // Setup verified telegram account and preferences
        TelegramAccount::create([
            'user_id' => $employee->id,
            'chat_id' => '998877',
            'verified_at' => now(),
            'is_active' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $employee->id,
            'in_app_enabled' => true,
            'telegram_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('tasks.store'), [
            'code' => 'TSK-ASSIGN-01',
            'title' => 'Setup development server',
            'description' => 'Install PHP and Composer',
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::PENDING->value,
            'due_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', ['code' => 'TSK-ASSIGN-01']);

        // 1. In-app notification created
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employee->id,
            'type' => TaskAssignedNotification::class,
        ]);

        // 2. Telegram delivery logged
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $employee->id,
            'channel' => 'telegram',
            'status' => 'sent',
            'provider_message_id' => '12345',
        ]);
    }

    public function test_creating_task_without_assignee_sends_no_notification(): void
    {
        Http::fake();

        $admin = User::factory()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)->post(route('tasks.store'), [
            'code' => 'TSK-UNASSIGNED',
            'title' => 'Unassigned task',
            'project_id' => $project->id,
            'assigned_to' => null,
            'priority' => TaskPriority::LOW->value,
            'status' => TaskStatus::PENDING->value,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => TaskAssignedNotification::class,
        ]);
        $this->assertDatabaseMissing('notification_deliveries', [
            'channel' => 'telegram',
        ]);
        Http::assertNothingSent();
    }

    public function test_telegram_disconnected_or_disabled_user_still_receives_in_app_notification(): void
    {
        Http::fake();

        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create();

        // User has no telegram account
        $this->actingAs($admin)->post(route('tasks.store'), [
            'code' => 'TSK-NO-TG',
            'title' => 'No Telegram User Task',
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::PENDING->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $employee->id,
            'type' => TaskAssignedNotification::class,
        ]);
        $this->assertDatabaseMissing('notification_deliveries', [
            'channel' => 'telegram',
        ]);
        Http::assertNothingSent();
    }

    public function test_telegram_api_failure_does_not_fail_task_creation(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);

        Http::fake([
            'api.telegram.org/bot*' => Http::response([
                'ok' => false,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 403),
        ]);

        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create();

        TelegramAccount::create([
            'user_id' => $employee->id,
            'chat_id' => '998877',
            'verified_at' => now(),
            'is_active' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $employee->id,
            'in_app_enabled' => true,
            'telegram_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('tasks.store'), [
            'code' => 'TSK-TG-FAIL',
            'title' => 'Task with telegram fail',
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'priority' => TaskPriority::HIGH->value,
            'status' => TaskStatus::PENDING->value,
        ]);

        $response->assertRedirect(route('tasks.index'));
        $this->assertDatabaseHas('tasks', ['code' => 'TSK-TG-FAIL']);

        // Delivery logged as failed
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $employee->id,
            'channel' => 'telegram',
            'status' => 'failed',
        ]);
    }

    public function test_reassigning_task_notifies_new_assignee_only(): void
    {
        $admin = User::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $project = Project::factory()->create();

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $userA->id,
            'created_by' => $admin->id,
        ]);

        // Clear notifications from setup
        $userA->notifications()->delete();
        $userB->notifications()->delete();

        // Reassign User A -> User B
        $response = $this->actingAs($admin)->put(route('tasks.update', $task), [
            'code' => $task->code,
            'title' => $task->title,
            'project_id' => $project->id,
            'assigned_to' => $userB->id,
            'priority' => $task->priority->value,
            'status' => $task->status->value,
        ]);

        $response->assertRedirect(route('tasks.index'));

        // User B received notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $userB->id,
            'type' => TaskAssignedNotification::class,
        ]);

        // User A did NOT receive notification
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $userA->id,
            'type' => TaskAssignedNotification::class,
        ]);
    }

    public function test_editing_task_without_changing_assignee_sends_no_notification(): void
    {
        $admin = User::factory()->create();
        $employee = User::factory()->create();
        $project = Project::factory()->create();

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'created_by' => $admin->id,
            'title' => 'Original Title',
        ]);

        $employee->notifications()->delete();

        // Update title only
        $this->actingAs($admin)->put(route('tasks.update', $task), [
            'code' => $task->code,
            'title' => 'Updated Title',
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'priority' => $task->priority->value,
            'status' => $task->status->value,
        ]);

        // No new assignment notification sent
        $this->assertDatabaseMissing('notifications', [
            'notifiable_id' => $employee->id,
            'type' => TaskAssignedNotification::class,
        ]);
    }

    public function test_telegram_message_contains_all_required_task_details(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Boss']);
        $employee = User::factory()->create(['name' => 'John Doe']);
        $project = Project::factory()->create(['name' => 'Alpha Project']);

        $task = Task::factory()->create([
            'code' => 'TSK-DETAILS-99',
            'title' => 'Deploy Application',
            'project_id' => $project->id,
            'assigned_to' => $employee->id,
            'created_by' => $admin->id,
            'priority' => TaskPriority::HIGH,
            'due_date' => now()->addDays(3),
        ]);

        $notification = new TaskAssignedNotification($task);
        $telegramData = $notification->toTelegram($employee);

        $this->assertStringContainsString('New Task Assigned', $telegramData['header']);
        $this->assertEquals('TSK-DETAILS-99', $telegramData['task_code']);
        $this->assertEquals('Deploy Application', $telegramData['task_title']);
        $this->assertEquals('Alpha Project', $telegramData['project_name']);
        $this->assertEquals('HIGH', $telegramData['priority']);
        $this->assertEquals('Admin Boss', $telegramData['assigned_by']);
        $this->assertNotEmpty($telegramData['task_url']);
    }
}
