<?php

namespace Tests\Feature;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurringTaskStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Project;
use App\Models\RecurringTask;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\User;
use App\Services\RecurringTaskService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    // 1. Admin can create recurring task.
    public function test_admin_can_create_recurring_task(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $project = Project::factory()->create();

        $response = $this->actingAs($admin)->post(route('recurring-tasks.store'), [
            'code' => 'REC-001',
            'title' => 'Daily Standup',
            'project_id' => $project->id,
            'priority' => 'medium',
            'frequency' => 'daily',
            'interval' => 1,
            'starts_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_tasks', [
            'code' => 'REC-001',
            'status' => 'active',
        ]);
    }

    // 2. Manager can create authorized recurring task.
    public function test_manager_can_create_recurring_task(): void
    {
        $manager = User::factory()->create(['role' => UserRole::MANAGER]);
        $project = Project::factory()->create();

        $response = $this->actingAs($manager)->post(route('recurring-tasks.store'), [
            'code' => 'REC-002',
            'title' => 'Weekly Report',
            'project_id' => $project->id,
            'priority' => 'medium',
            'frequency' => 'weekly',
            'interval' => 1,
            'starts_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('recurring_tasks', ['code' => 'REC-002']);
    }

    // 3. Employee cannot create recurring task.
    public function test_employee_cannot_create_recurring_task(): void
    {
        $employee = User::factory()->create(['role' => UserRole::EMPLOYEE]);
        $project = Project::factory()->create();

        $response = $this->actingAs($employee)->post(route('recurring-tasks.store'), [
            'code' => 'REC-003',
            'title' => 'Employee Report',
            'project_id' => $project->id,
            'priority' => 'medium',
            'frequency' => 'daily',
            'interval' => 1,
            'starts_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertStatus(403);
    }

    // 4. Validation rejects invalid interval.
    public function test_validation_rejects_invalid_interval(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->post(route('recurring-tasks.store'), [
            'interval' => 0, // Invalid
        ]);

        $response->assertSessionHasErrors('interval');
    }

    // 5. Validation rejects invalid dates.
    public function test_validation_rejects_invalid_dates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $response = $this->actingAs($admin)->post(route('recurring-tasks.store'), [
            'starts_at' => 'invalid-date',
            'ends_at' => '2020-01-01', // Before starts_at (if starts_at was valid)
        ]);

        $response->assertSessionHasErrors(['starts_at']);
    }

    // 6. Daily recurrence calculates correctly.
    public function test_daily_recurrence_calculates_correctly(): void
    {
        $service = app(RecurringTaskService::class);
        $date = Carbon::parse('2026-09-01 10:00:00');

        $next = $service->calculateNextRun(RecurrenceFrequency::DAILY, $date, 1);

        $this->assertEquals('2026-09-02 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    // 7. Weekly recurrence calculates correctly.
    public function test_weekly_recurrence_calculates_correctly(): void
    {
        $service = app(RecurringTaskService::class);
        $date = Carbon::parse('2026-09-01 10:00:00'); // Tuesday

        $next = $service->calculateNextRun(RecurrenceFrequency::WEEKLY, $date, 1);

        $this->assertEquals('2026-09-08 10:00:00', $next->format('Y-m-d H:i:s')); // Next Tuesday
    }

    // 8. Monthly recurrence calculates correctly.
    public function test_monthly_recurrence_calculates_correctly(): void
    {
        $service = app(RecurringTaskService::class);
        $date = Carbon::parse('2026-09-01 10:00:00');

        $next = $service->calculateNextRun(RecurrenceFrequency::MONTHLY, $date, 1);

        $this->assertEquals('2026-10-01 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    // 9. Month-end calculation works.
    public function test_month_end_calculation_works(): void
    {
        $service = app(RecurringTaskService::class);
        $date = Carbon::parse('2026-01-31 10:00:00');

        $next = $service->calculateNextRun(RecurrenceFrequency::MONTHLY, $date, 1);

        // 2026 is not a leap year, so Feb 28
        $this->assertEquals('2026-02-28 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    // 10. Interval works.
    public function test_interval_works(): void
    {
        $service = app(RecurringTaskService::class);
        $date = Carbon::parse('2026-09-01 10:00:00');

        $next = $service->calculateNextRun(RecurrenceFrequency::DAILY, $date, 3);

        $this->assertEquals('2026-09-04 10:00:00', $next->format('Y-m-d H:i:s'));
    }

    // 11. Future recurrence is not processed.
    public function test_future_recurrence_is_not_processed(): void
    {
        $rt = RecurringTask::factory()->create(['next_run_at' => now()->addDay()]);

        $result = app(RecurringTaskService::class)->processDueRecurringTasks();

        $this->assertEquals(0, $result['generated']);
    }

    // 12-20. Due recurrence generates Task correctly and updates fields
    public function test_due_recurrence_generates_task_and_updates_correctly(): void
    {
        $rt = RecurringTask::factory()->create([
            'next_run_at' => now()->subMinute(),
            'frequency' => RecurrenceFrequency::DAILY,
            'interval' => 1,
            'priority' => TaskPriority::HIGH,
        ]);

        $result = app(RecurringTaskService::class)->processDueRecurringTasks();

        $this->assertEquals(1, $result['generated']);

        // 13. Project is correct
        // 14. Assignee is correct
        // 15. Priority is correct
        // 16. Status is pending
        // 17. recurring_task_id is set
        $task = Task::where('recurring_task_id', $rt->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals($rt->project_id, $task->project_id);
        $this->assertEquals($rt->assigned_to, $task->assigned_to);
        $this->assertEquals(TaskPriority::HIGH, $task->priority);
        $this->assertEquals(TaskStatus::PENDING, $task->status);

        // 18. Occurrence record is created
        $this->assertDatabaseHas('recurring_task_occurrences', [
            'recurring_task_id' => $rt->id,
            'task_id' => $task->id,
        ]);

        // 19. next_run_at advances
        // 20. last_run_at updates
        $rt->refresh();
        $this->assertTrue($rt->next_run_at->isFuture());
        $this->assertNotNull($rt->last_run_at);
    }

    // 21. Same occurrence cannot be generated twice (Idempotency).
    public function test_same_occurrence_cannot_be_generated_twice(): void
    {
        $rt = RecurringTask::factory()->create(['next_run_at' => now()->subMinute()]);

        // Process it once
        $service = app(RecurringTaskService::class);
        $service->processDefinition($rt);

        // Reset next_run_at to the same time to force it to try processing the same occurrence
        $originalRun = $rt->last_run_at;
        $rt->update(['next_run_at' => $originalRun]);

        // Try again
        $result = $service->processDefinition($rt);

        $this->assertEquals('duplicate', $result);
    }

    // 23-25. Paused/Cancelled/Ended recurrences are skipped.
    public function test_inactive_recurrences_are_skipped(): void
    {
        RecurringTask::factory()->create(['status' => RecurringTaskStatus::PAUSED, 'next_run_at' => now()->subMinute()]);
        RecurringTask::factory()->create(['status' => RecurringTaskStatus::CANCELLED, 'next_run_at' => now()->subMinute()]);
        RecurringTask::factory()->create(['ends_at' => now()->subDay(), 'next_run_at' => now()->addMinute()]); // completed logic normally handles this, but hasEnded guards it anyway

        $result = app(RecurringTaskService::class)->processDueRecurringTasks();

        $this->assertEquals(0, $result['generated']);
    }

    // 27-29. Auto reminder creates TaskReminder properly or skips if past.
    public function test_auto_reminder_is_skipped_if_calculated_time_is_in_past(): void
    {
        $rt = RecurringTask::factory()->create([
            'next_run_at' => now()->subMinute(),
            'auto_create_reminder' => true,
            'reminder_offset_minutes' => 30, // 30 minutes before due -> always past since due is <= now
        ]);

        app(RecurringTaskService::class)->processDueRecurringTasks();

        $task = Task::where('recurring_task_id', $rt->id)->first();

        // Reminder should be skipped because it would be in the past
        $this->assertDatabaseMissing('task_reminders', [
            'task_id' => $task->id,
        ]);
    }
}
