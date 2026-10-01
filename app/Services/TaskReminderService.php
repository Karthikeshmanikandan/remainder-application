<?php

namespace App\Services;

use App\Enums\ReminderStatus;
use App\Models\TaskReminder;
use App\Notifications\TaskReminderNotification;
use Illuminate\Support\Facades\Log;

class TaskReminderService
{
    /**
     * Process all due pending reminders.
     *
     * @return array{processed: int, skipped: int}
     */
    public function processDueReminders(): array
    {
        $processed = 0;
        $skipped = 0;

        TaskReminder::query()
            ->where('status', ReminderStatus::PENDING)
            ->where('remind_at', '<=', now())
            ->with(['task.project', 'user'])
            ->chunkById(100, function ($reminders) use (&$processed, &$skipped) {
                foreach ($reminders as $reminder) {
                    if ($this->processReminder($reminder)) {
                        $processed++;
                    } else {
                        $skipped++;
                    }
                }
            });

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    /**
     * Process a single reminder idempotently using an atomic status update.
     * Uses a conditional UPDATE that only succeeds if the status is still "pending",
     * which prevents race conditions and duplicate processing without needing
     * SELECT FOR UPDATE (which SQLite does not support).
     *
     * Returns true if processed, false if skipped.
     */
    public function processReminder(TaskReminder $reminder): bool
    {
        // Atomic update: only flips to triggered if still pending
        $affected = TaskReminder::query()
            ->where('id', $reminder->id)
            ->where('status', ReminderStatus::PENDING)
            ->update([
                'status' => ReminderStatus::TRIGGERED,
                'triggered_at' => now(),
            ]);

        if ($affected === 0) {
            // Another process got here first, or it was cancelled — skip
            return false;
        }

        // Reload the fresh reminder to send notification
        $fresh = $reminder->fresh(['task.project', 'user']);

        try {
            $fresh->user->notify(new TaskReminderNotification($fresh));
        } catch (\Throwable $e) {
            Log::error('Failed to send reminder notification', [
                'reminder_id' => $fresh->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * Cancel a pending reminder. Returns false if the reminder is not pending.
     */
    public function cancelReminder(TaskReminder $reminder): bool
    {
        if (! $reminder->isPending()) {
            return false;
        }

        $reminder->update(['status' => ReminderStatus::CANCELLED]);

        return true;
    }
}
