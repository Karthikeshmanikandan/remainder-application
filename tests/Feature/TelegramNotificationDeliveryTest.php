<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Models\NotificationPreference;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\TelegramAccount;
use App\Models\User;
use App\Services\TaskReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_telegram_user_receives_telegram_notification_when_reminder_is_processed(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);

        Http::fake([
            'api.telegram.org/bot*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 9988],
            ], 200),
        ]);

        $user = User::factory()->create();
        TelegramAccount::create([
            'user_id' => $user->id,
            'chat_id' => '12345678',
            'verified_at' => now(),
            'is_active' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $user->id,
            'in_app_enabled' => true,
            'telegram_enabled' => true,
        ]);

        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $user->id,
            'due_date' => now()->addHour(),
        ]);
        $reminder = TaskReminder::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'remind_at' => now()->subMinute(),
            'status' => ReminderStatus::PENDING,
        ]);

        $service = app(TaskReminderService::class);
        $result = $service->processDueReminders();

        $this->assertEquals(1, $result['processed']);
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $user->id,
            'channel' => 'telegram',
            'status' => 'sent',
            'provider_message_id' => '9988',
        ]);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
        ]);
    }

    public function test_unverified_or_disabled_telegram_user_does_not_send_telegram_message(): void
    {
        Http::fake();

        $user = User::factory()->create();
        // Unverified telegram account
        TelegramAccount::create([
            'user_id' => $user->id,
            'verification_code' => 'TG-123456',
            'verification_expires_at' => now()->addMinutes(10),
            'is_active' => false,
        ]);

        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $user->id,
        ]);
        TaskReminder::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'remind_at' => now()->subMinute(),
            'status' => ReminderStatus::PENDING,
        ]);

        $service = app(TaskReminderService::class);
        $result = $service->processDueReminders();

        $this->assertEquals(1, $result['processed']);
        Http::assertNothingSent();
        $this->assertDatabaseMissing('notification_deliveries', [
            'user_id' => $user->id,
            'channel' => 'telegram',
        ]);
    }

    public function test_telegram_api_failure_does_not_break_reminder_processing_or_in_app_notification(): void
    {
        Http::fake([
            'api.telegram.org/bot*' => Http::response([
                'ok' => false,
                'description' => 'Forbidden: bot was blocked by the user',
            ], 403),
        ]);

        $user = User::factory()->create();
        TelegramAccount::create([
            'user_id' => $user->id,
            'chat_id' => '12345678',
            'verified_at' => now(),
            'is_active' => true,
        ]);
        NotificationPreference::create([
            'user_id' => $user->id,
            'in_app_enabled' => true,
            'telegram_enabled' => true,
        ]);

        $project = Project::factory()->create();
        $task = Task::factory()->create([
            'project_id' => $project->id,
            'assigned_to' => $user->id,
        ]);
        $reminder = TaskReminder::factory()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'remind_at' => now()->subMinute(),
            'status' => ReminderStatus::PENDING,
        ]);

        $service = app(TaskReminderService::class);
        $result = $service->processDueReminders();

        $this->assertEquals(1, $result['processed']);
        $reminder->refresh();
        $this->assertEquals(ReminderStatus::TRIGGERED, $reminder->status);

        // In-app notification still created
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
        ]);

        // Telegram delivery failed logged
        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $user->id,
            'channel' => 'telegram',
            'status' => 'failed',
        ]);
    }
}
