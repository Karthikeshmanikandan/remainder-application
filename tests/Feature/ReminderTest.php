<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\User;
use App\Notifications\TaskReminderNotification;
use App\Services\TaskReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. Authenticated user can create a reminder
    // -------------------------------------------------------------------------
    public function test_authenticated_user_can_create_reminder(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $task = Task::factory()->create();
        $recipient = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('tasks.reminders.store', $task), [
            'user_id' => $recipient->id,
            'remind_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('tasks.show', $task));
        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $task->id,
            'user_id' => $recipient->id,
            'status' => ReminderStatus::PENDING->value,
        ]);
    }

    // -------------------------------------------------------------------------
    // 2. Guest cannot create a reminder
    // -------------------------------------------------------------------------
    public function test_guest_cannot_create_reminder(): void
    {
        $task = Task::factory()->create();
        $user = User::factory()->create();

        $this->post(route('tasks.reminders.store', $task), [
            'user_id' => $user->id,
            'remind_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ])->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // 3. Employee cannot set reminder for another user
    // -------------------------------------------------------------------------
    public function test_employee_cannot_create_reminder_for_another_user(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $other = User::factory()->create();
        $task = Task::factory()->create();

        $this->actingAs($employee)->post(route('tasks.reminders.store', $task), [
            'user_id' => $other->id,
            'remind_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ])->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // 4. Reminder validation — past time is rejected
    // -------------------------------------------------------------------------
    public function test_reminder_validation_rejects_past_time(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN]);
        $task = Task::factory()->create();

        $this->actingAs($user)->post(route('tasks.reminders.store', $task), [
            'user_id' => $user->id,
            'remind_at' => now()->subHour()->format('Y-m-d H:i:s'),
        ])->assertSessionHasErrors('remind_at');
    }

    // -------------------------------------------------------------------------
    // 5 & 6. Reminder belongs to task and recipient
    // -------------------------------------------------------------------------
    public function test_reminder_belongs_to_task_and_recipient(): void
    {
        $reminder = TaskReminder::factory()->create();

        $this->assertInstanceOf(Task::class, $reminder->task);
        $this->assertInstanceOf(User::class, $reminder->user);
    }

    // -------------------------------------------------------------------------
    // 7. New reminder starts as pending
    // -------------------------------------------------------------------------
    public function test_new_reminder_starts_as_pending(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $task = Task::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($admin)->post(route('tasks.reminders.store', $task), [
            'user_id' => $recipient->id,
            'remind_at' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);

        $reminder = TaskReminder::first();
        $this->assertEquals(ReminderStatus::PENDING, $reminder->status);
        $this->assertNull($reminder->triggered_at);
    }

    // -------------------------------------------------------------------------
    // 8. Authorised user can cancel a pending reminder
    // -------------------------------------------------------------------------
    public function test_authorised_user_can_cancel_pending_reminder(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN]);
        $reminder = TaskReminder::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('tasks.reminders.destroy', [$reminder->task, $reminder]))
            ->assertRedirect();

        $this->assertEquals(ReminderStatus::CANCELLED, $reminder->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // 9. Triggered reminder cannot be cancelled
    // -------------------------------------------------------------------------
    public function test_triggered_reminder_cannot_be_cancelled(): void
    {
        $user = User::factory()->create(['role' => UserRole::ADMIN]);
        $reminder = TaskReminder::factory()->triggered()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->delete(route('tasks.reminders.destroy', [$reminder->task, $reminder]))
            ->assertRedirect();

        // Should stay triggered
        $this->assertEquals(ReminderStatus::TRIGGERED, $reminder->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // 10. Cancelled reminder is never processed
    // -------------------------------------------------------------------------
    public function test_cancelled_reminder_is_never_processed(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->cancelled()->due()->create();

        $service = app(TaskReminderService::class);
        $result = $service->processDueReminders();

        $this->assertEquals(0, $result['processed']);
        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // 11. Due reminder is processed
    // -------------------------------------------------------------------------
    public function test_due_reminder_is_processed(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->due()->create();

        $service = app(TaskReminderService::class);
        $result = $service->processDueReminders();

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(ReminderStatus::TRIGGERED, $reminder->fresh()->status);
        $this->assertNotNull($reminder->fresh()->triggered_at);

        Notification::assertSentTo($reminder->user, TaskReminderNotification::class);
    }

    // -------------------------------------------------------------------------
    // 12. Processing creates exactly one notification
    // -------------------------------------------------------------------------
    public function test_processing_creates_exactly_one_notification(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->due()->create();

        app(TaskReminderService::class)->processDueReminders();

        Notification::assertSentToTimes($reminder->user, TaskReminderNotification::class, 1);
    }

    // -------------------------------------------------------------------------
    // 13. triggered_at is stored
    // -------------------------------------------------------------------------
    public function test_triggered_at_is_stored(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->due()->create();

        app(TaskReminderService::class)->processDueReminders();

        $this->assertNotNull($reminder->fresh()->triggered_at);
    }

    // -------------------------------------------------------------------------
    // 14. Running processor twice does NOT create duplicate notification
    // -------------------------------------------------------------------------
    public function test_processor_is_idempotent(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->due()->create();

        $service = app(TaskReminderService::class);
        $service->processDueReminders();
        $service->processDueReminders(); // second run

        Notification::assertSentToTimes($reminder->user, TaskReminderNotification::class, 1);
    }

    // -------------------------------------------------------------------------
    // 15. Future reminder is skipped
    // -------------------------------------------------------------------------
    public function test_future_reminder_is_skipped(): void
    {
        Notification::fake();
        TaskReminder::factory()->create(['remind_at' => now()->addHour()]);

        $result = app(TaskReminderService::class)->processDueReminders();

        $this->assertEquals(0, $result['processed']);
        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // 16. Already-triggered reminder is skipped on second run
    // -------------------------------------------------------------------------
    public function test_already_triggered_reminder_is_skipped(): void
    {
        Notification::fake();
        TaskReminder::factory()->triggered()->create();

        $result = app(TaskReminderService::class)->processDueReminders();

        $this->assertEquals(0, $result['processed']);
        Notification::assertNothingSent();
    }
}
