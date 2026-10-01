<?php

namespace App\Services;

use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessItemStatus;
use App\Enums\ProcessStatus;
use App\Models\Process;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Notifications\ProcessDueNotification;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessScheduleService
{
    public function __construct(
        private ProcessService $processService,
        private TelegramService $telegramService
    ) {}

    /**
     * Process all active due processes and generate executions idempotently.
     *
     * @return array{generated: int, skipped: int, duplicates_prevented: int}
     */
    public function processDueProcesses(): array
    {
        $generated = 0;
        $skipped = 0;
        $duplicatesPrevented = 0;

        Process::query()
            ->where('status', ProcessStatus::ACTIVE)
            ->where('next_run_at', '<=', now())
            ->with(['department', 'responsibleUser', 'responsibleTelegramEmployee.telegramAccount', 'enabledItems'])
            ->chunkById(100, function ($processes) use (&$generated, &$skipped, &$duplicatesPrevented) {
                foreach ($processes as $process) {
                    $result = $this->processSingleDefinition($process);

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
     * Process a single process definition.
     * Returns 'generated', 'duplicate', or 'skipped'.
     */
    public function processSingleDefinition(Process $process): string
    {
        if (! $process->isActive() || $process->hasEnded()) {
            return 'skipped';
        }

        $scheduledFor = $process->next_run_at;
        $occurrenceKey = $this->buildOccurrenceKey($process, $scheduledFor);

        try {
            $execution = DB::transaction(function () use ($process, $scheduledFor, $occurrenceKey) {
                // 1. Create ProcessExecution with snapshots
                $execution = ProcessExecution::create([
                    'organization_id' => $process->organization_id ?? 1,
                    'process_id' => $process->id,
                    'occurrence_key' => $occurrenceKey,
                    'scheduled_for' => $scheduledFor,
                    'started_at' => null,
                    'completed_at' => null,
                    'status' => ProcessExecutionStatus::PENDING,
                    'completed_by' => null,
                    'responsible_name_snapshot' => $process->responsible_name,
                    'responsible_type_snapshot' => $process->responsible_type_label,
                ]);

                // 2. Create execution items for all enabled process items
                $enabledItems = $process->enabledItems;
                foreach ($enabledItems as $pItem) {
                    ProcessExecutionItem::create([
                        'process_execution_id' => $execution->id,
                        'process_item_id' => $pItem->id,
                        'question_snapshot' => $pItem->question,
                        'response_type' => $pItem->response_type,
                        'response' => null,
                        'notes' => null,
                        'answered_by' => null,
                        'answered_at' => null,
                        'status' => ProcessItemStatus::PENDING,
                    ]);
                }

                // 3. Advance next_run_at
                $nextRun = $this->processService->calculateNextRun(
                    $process->frequency,
                    $scheduledFor,
                    $process->interval,
                    $process->reminder_time
                );

                $process->update([
                    'next_run_at' => $nextRun,
                    'last_run_at' => $scheduledFor,
                    'status' => ($process->ends_at && $nextRun->gt($process->ends_at))
                        ? ProcessStatus::COMPLETED
                        : $process->status,
                ]);

                return $execution;
            });

        } catch (UniqueConstraintViolationException $e) {
            // Hard idempotency guarantee: Occurrence was already generated
            $nextRun = $this->processService->calculateNextRun(
                $process->frequency,
                $scheduledFor,
                $process->interval,
                $process->reminder_time
            );
            $process->update(['next_run_at' => $nextRun]);

            return 'duplicate';
        } catch (\Throwable $e) {
            Log::error('ProcessScheduleService: Failed to process process definition', [
                'process_id' => $process->id,
                'error' => $e->getMessage(),
            ]);

            return 'skipped';
        }

        // 4. Send due reminder notifications to responsible person
        if ($process->reminder_enabled) {
            $this->sendProcessNotification($process, $execution, $scheduledFor);
        }

        return 'generated';
    }

    /**
     * Send due reminder notification for a process execution.
     */
    public function sendProcessNotification(Process $process, ProcessExecution $execution, Carbon $scheduledFor): void
    {
        $process->loadMissing(['responsibleUser', 'responsibleTelegramEmployee.telegramAccount']);
        $execution->loadMissing(['items.processItem']);

        if ($process->responsibleUser) {
            try {
                $process->responsibleUser->notify(new ProcessDueNotification($process, $execution));
            } catch (\Throwable $e) {
                Log::error('ProcessScheduleService: Failed to notify responsible user', [
                    'process_id' => $process->id,
                    'execution_id' => $execution->id,
                    'user_id' => $process->responsible_user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($process->responsibleTelegramEmployee) {
            try {
                $employee = $process->responsibleTelegramEmployee;
                if ($employee->isTelegramConnected() && $employee->telegramAccount->chat_id && ($process->telegram_enabled ?? true)) {
                    $firstItem = $execution->items->first();
                    $questionText = $firstItem ? $firstItem->question_snapshot : 'Checklist items pending.';
                    $responseHelp = match ($firstItem?->response_type?->value ?? 'yes_no') {
                        'yes_no_na' => 'Reply YES / NO / NA',
                        'number' => 'Reply with a number',
                        'text' => 'Reply with your response text',
                        default => 'Reply YES / NO',
                    };

                    $msg = "📋 <b>{$process->name}</b>\n\n";
                    $msg .= "Scheduled For: {$scheduledFor->format('d M Y, h:i A')}\n\n";
                    $msg .= "Progress: 0/{$execution->items->count()}\n\n";
                    $msg .= "1. {$questionText}\n\n";
                    $msg .= "<i>{$responseHelp}</i>\n";
                    $msg .= 'Or send /checklists to view all open items.';

                    $this->telegramService->sendMessage($employee->telegramAccount->chat_id, $msg);
                }
            } catch (\Throwable $e) {
                Log::error('ProcessScheduleService: Failed to notify telegram employee', [
                    'process_id' => $process->id,
                    'execution_id' => $execution->id,
                    'employee_id' => $process->responsible_telegram_employee_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Generate an execution directly for a specific date (useful for tests and manual dispatch).
     */
    public function generateExecutionForDate(Process $process, Carbon $scheduledFor): ProcessExecution
    {
        $occurrenceKey = $this->buildOccurrenceKey($process, $scheduledFor);

        $execution = DB::transaction(function () use ($process, $scheduledFor, $occurrenceKey) {
            $execution = ProcessExecution::create([
                'organization_id' => $process->organization_id ?? 1,
                'process_id' => $process->id,
                'occurrence_key' => $occurrenceKey,
                'scheduled_for' => $scheduledFor,
                'started_at' => null,
                'completed_at' => null,
                'status' => ProcessExecutionStatus::PENDING,
                'completed_by' => null,
                'responsible_name_snapshot' => $process->responsible_name,
                'responsible_type_snapshot' => $process->responsible_type_label,
            ]);

            $enabledItems = $process->enabledItems;
            foreach ($enabledItems as $pItem) {
                ProcessExecutionItem::create([
                    'process_execution_id' => $execution->id,
                    'process_item_id' => $pItem->id,
                    'question_snapshot' => $pItem->question,
                    'response_type' => $pItem->response_type,
                    'response' => null,
                    'notes' => null,
                    'answered_by' => null,
                    'answered_at' => null,
                    'status' => ProcessItemStatus::PENDING,
                ]);
            }

            return $execution;
        });

        if ($process->reminder_enabled ?? true) {
            $this->sendProcessNotification($process, $execution, $scheduledFor);
        }

        return $execution;
    }

    /**
     * Build deterministic occurrence key for process execution.
     */
    public function buildOccurrenceKey(Process $process, Carbon $scheduledFor): string
    {
        return sprintf('%d-%s', $process->id, $scheduledFor->format('Y-m-d\TH:i'));
    }
}
