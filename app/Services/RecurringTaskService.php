<?php

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurringTaskStatus;
use App\Enums\ReminderStatus;
use App\Enums\TaskStatus;
use App\Models\RecurringTask;
use App\Models\RecurringTaskOccurrence;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Notifications\TaskAssignedNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringTaskService
{
    /**
     * Process all due active recurring definitions.
     *
     * Catch-up strategy: if the scheduler was offline, we process the CURRENT
     * due occurrence only (advance next_run_at by one period) rather than
     * generating every missed occurrence. This prevents unbounded task creation.
     *
     * @return array{generated: int, skipped: int, duplicates_prevented: int}
     */
    public function processDueRecurringTasks(): array
    {
        $generated = 0;
        $skipped = 0;
        $duplicatesPrevented = 0;

        RecurringTask::query()
            ->where('status', RecurringTaskStatus::ACTIVE)
            ->where('next_run_at', '<=', now())
            ->with(['project', 'assignee'])
            ->chunkById(100, function ($definitions) use (&$generated, &$skipped, &$duplicatesPrevented) {
                foreach ($definitions as $definition) {
                    $result = $this->processDefinition($definition);

                    if ($result === 'generated') {
                        $generated++;
                    } elseif ($result === 'duplicate') {
                        $duplicatesPrevented++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return [
            'generated' => $generated,
            'skipped' => $skipped,
            'duplicates_prevented' => $duplicatesPrevented,
        ];
    }

    /**
     * Process a single recurring task definition.
     * Returns 'generated', 'duplicate', or 'skipped'.
     */
    public function processDefinition(RecurringTask $definition): string
    {
        // Guard: skip anything not active
        if (! $definition->isActive()) {
            return 'skipped';
        }

        // Guard: skip if ended
        if ($definition->hasEnded()) {
            return 'skipped';
        }

        $scheduledFor = $definition->next_run_at;
        $occurrenceKey = $this->buildOccurrenceKey($definition, $scheduledFor);

        // Attempt atomic occurrence record creation — unique constraint prevents duplicates
        try {
            $occurrence = DB::transaction(function () use ($definition, $scheduledFor, $occurrenceKey) {
                // Insert occurrence record — will throw on duplicate key violation
                $occurrence = RecurringTaskOccurrence::create([
                    'recurring_task_id' => $definition->id,
                    'occurrence_key' => $occurrenceKey,
                    'scheduled_for' => $scheduledFor,
                ]);

                // Generate the normal Task
                $taskCode = $this->buildTaskCode($definition, $scheduledFor);
                $task = Task::create([
                    'organization_id' => $definition->organization_id ?? 1,
                    'code' => $taskCode,
                    'title' => $definition->title,
                    'description' => $definition->description,
                    'project_id' => $definition->project_id,
                    'assigned_to' => $definition->assigned_to,
                    'assigned_telegram_employee_id' => $definition->assigned_telegram_employee_id,
                    'created_by' => $definition->created_by,
                    'priority' => $definition->priority,
                    'status' => TaskStatus::PENDING,
                    'due_date' => $scheduledFor,
                    'recurring_task_id' => $definition->id,
                ]);

                // Link the occurrence to the generated task
                $occurrence->update(['task_id' => $task->id]);

                // Advance next_run_at AFTER successful task creation
                $nextRun = $this->calculateNextRun($definition->frequency, $scheduledFor, $definition->interval);

                $definition->update([
                    'next_run_at' => $nextRun,
                    'last_run_at' => $scheduledFor,
                    // Mark completed if next run exceeds ends_at
                    'status' => ($definition->ends_at && $nextRun->gt($definition->ends_at))
                        ? RecurringTaskStatus::COMPLETED
                        : $definition->status,
                ]);

                return ['occurrence' => $occurrence, 'task' => $task];
            });

        } catch (UniqueConstraintViolationException $e) {
            // Duplicate occurrence_key — idempotency in action
            // Advance next_run_at so the scheduler moves forward
            $nextRun = $this->calculateNextRun($definition->frequency, $scheduledFor, $definition->interval);
            $definition->update(['next_run_at' => $nextRun]);

            return 'duplicate';
        } catch (\Throwable $e) {
            Log::error('RecurringTaskService: failed to process definition', [
                'recurring_task_id' => $definition->id,
                'error' => $e->getMessage(),
            ]);

            return 'skipped';
        }

        $task = $occurrence['task'];
        $occ = $occurrence['occurrence'];

        // Notify assigned user of the newly generated task
        if ($task->assigned_to && $task->assignee) {
            try {
                $task->assignee->notify(new TaskAssignedNotification($task));
            } catch (\Throwable $e) {
                Log::error('RecurringTaskService: failed to notify assignee of generated recurring task', [
                    'task_id' => $task->id,
                    'assigned_to' => $task->assigned_to,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Auto-create TaskReminder if configured
        if ($definition->auto_create_reminder && $definition->assigned_to) {
            $this->createReminderForTask($task, $definition);
        }

        return 'generated';
    }

    /**
     * Calculate the next run datetime.
     *
     * Monthly behavior: clamp to last valid day.
     * e.g. Jan 31 + 1 month → Feb 28/29 (not overflow to March)
     */
    public function calculateNextRun(RecurrenceFrequency $frequency, Carbon $from, int $interval = 1): Carbon
    {
        $next = $from->copy();

        return match ($frequency) {
            RecurrenceFrequency::DAILY => $next->addDays($interval),
            RecurrenceFrequency::WEEKLY => $next->addWeeks($interval),
            RecurrenceFrequency::MONTHLY => $this->addMonthsClamped($next, $interval),
        };
    }

    /**
     * Add months while clamping to the last valid day of the target month.
     *
     * Example: Jan 31 + 1 → Feb 28/29 (not Mar 2/3)
     * Carbon's addMonthsNoOverflow() does exactly this.
     */
    private function addMonthsClamped(Carbon $date, int $months): Carbon
    {
        return $date->copy()->addMonthsNoOverflow($months);
    }

    /**
     * Build a deterministic occurrence key: "{recurring_task_id}-{ISO8601 without seconds}"
     * Stable, readable, and unique per run.
     */
    public function buildOccurrenceKey(RecurringTask $definition, Carbon $scheduledFor): string
    {
        return sprintf('%d-%s', $definition->id, $scheduledFor->format('Y-m-d\TH:i'));
    }

    /**
     * Build a human-readable unique task code.
     * Format: REC-{definition_code}-{YYYYMMDD}
     */
    private function buildTaskCode(RecurringTask $definition, Carbon $scheduledFor): string
    {
        $base = strtoupper($definition->code).'-'.$scheduledFor->format('Ymd');

        // Ensure uniqueness — append index suffix if code already taken
        $code = $base;
        $suffix = 1;
        while (Task::where('code', $code)->exists()) {
            $code = $base.'-'.$suffix;
            $suffix++;
        }

        return $code;
    }

    /**
     * Create a TaskReminder for a generated task using the recurring definition's offset.
     *
     * If the calculated reminder time is already in the past, we skip reminder creation
     * and log a warning rather than silently creating an invalid reminder.
     */
    private function createReminderForTask(Task $task, RecurringTask $definition): void
    {
        if (! $task->due_date) {
            return;
        }

        $remindAt = $task->due_date->copy()->subMinutes($definition->reminder_offset_minutes);

        if ($remindAt->isPast()) {
            Log::warning('RecurringTaskService: skipped auto-reminder because calculated time is in the past', [
                'task_id' => $task->id,
                'remind_at' => $remindAt->toIso8601String(),
            ]);

            return;
        }

        TaskReminder::create([
            'task_id' => $task->id,
            'user_id' => $definition->assigned_to,
            'remind_at' => $remindAt,
            'status' => ReminderStatus::PENDING,
        ]);
    }

    /**
     * Calculate the initial next_run_at for a new recurring task definition.
     * starts_at is always the first run.
     */
    public function calculateInitialNextRun(Carbon $startsAt): Carbon
    {
        return $startsAt->copy();
    }
}
