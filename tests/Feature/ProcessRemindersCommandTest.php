<?php

namespace Tests\Feature;

use App\Enums\ReminderStatus;
use App\Models\TaskReminder;
use App\Notifications\TaskReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProcessRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // 1. Command runs with no pending reminders
    // -------------------------------------------------------------------------
    public function test_command_runs_with_no_pending_reminders(): void
    {
        $this->artisan('reminders:process')
            ->assertSuccessful()
            ->expectsOutput('Processing task reminders...');
    }

    // -------------------------------------------------------------------------
    // 2. Command processes one due reminder
    // -------------------------------------------------------------------------
    public function test_command_processes_one_due_reminder(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->due()->create();

        $this->artisan('reminders:process')->assertSuccessful();

        $this->assertEquals(ReminderStatus::TRIGGERED, $reminder->fresh()->status);
        Notification::assertSentTo($reminder->user, TaskReminderNotification::class);
    }

    // -------------------------------------------------------------------------
    // 3. Command processes multiple due reminders
    // -------------------------------------------------------------------------
    public function test_command_processes_multiple_due_reminders(): void
    {
        Notification::fake();
        $reminders = TaskReminder::factory()->due()->count(3)->create();

        $this->artisan('reminders:process')->assertSuccessful();

        foreach ($reminders as $reminder) {
            $this->assertEquals(ReminderStatus::TRIGGERED, $reminder->fresh()->status);
        }
    }

    // -------------------------------------------------------------------------
    // 4. Future reminder is skipped
    // -------------------------------------------------------------------------
    public function test_command_skips_future_reminder(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->create(['remind_at' => now()->addHour()]);

        $this->artisan('reminders:process')->assertSuccessful();

        $this->assertEquals(ReminderStatus::PENDING, $reminder->fresh()->status);
        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // 5. Cancelled reminder is skipped
    // -------------------------------------------------------------------------
    public function test_command_skips_cancelled_reminder(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->cancelled()->due()->create();

        $this->artisan('reminders:process')->assertSuccessful();

        $this->assertEquals(ReminderStatus::CANCELLED, $reminder->fresh()->status);
        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // 6. Already-triggered reminder is skipped
    // -------------------------------------------------------------------------
    public function test_command_skips_already_triggered_reminder(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->triggered()->create();

        $this->artisan('reminders:process')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // 7. Command is idempotent — running twice sends exactly one notification
    // -------------------------------------------------------------------------
    public function test_command_is_idempotent(): void
    {
        Notification::fake();
        $reminder = TaskReminder::factory()->due()->create();

        $this->artisan('reminders:process')->assertSuccessful();
        $this->artisan('reminders:process')->assertSuccessful(); // second run

        Notification::assertSentToTimes($reminder->user, TaskReminderNotification::class, 1);
    }
}
