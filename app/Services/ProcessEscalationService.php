<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ProcessEscalationStatus;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessStatus;
use App\Enums\UserRole;
use App\Models\Process;
use App\Models\ProcessEscalationEvent;
use App\Models\ProcessEscalationRule;
use App\Models\ProcessExecution;
use App\Models\User;
use App\Notifications\ProcessEscalationNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessEscalationService
{
    /**
     * Process all eligible escalation rules against pending/in-progress executions.
     *
     * @return array{processed_executions: int, rules_evaluated: int, escalations_triggered: int, already_triggered: int, skipped: int, notification_failures: int}
     */
    public function processEscalations(): array
    {
        $processedExecutionsCount = 0;
        $rulesEvaluatedCount = 0;
        $escalationsTriggeredCount = 0;
        $alreadyTriggeredCount = 0;
        $skippedCount = 0;
        $notificationFailuresCount = 0;

        $activeProcesses = Process::where('status', ProcessStatus::ACTIVE)
            ->whereHas('activeEscalationRules')
            ->with(['activeEscalationRules.escalateToUser', 'department', 'responsibleUser'])
            ->get();

        foreach ($activeProcesses as $process) {
            $rules = $process->activeEscalationRules;

            foreach ($rules as $rule) {
                $rulesEvaluatedCount++;

                // Execution must be PENDING or IN_PROGRESS and past the escalation threshold
                $thresholdTime = now()->subMinutes($rule->delay_minutes);

                $eligibleExecutions = ProcessExecution::where('process_id', $process->id)
                    ->whereIn('status', [ProcessExecutionStatus::PENDING, ProcessExecutionStatus::IN_PROGRESS])
                    ->where('scheduled_for', '<=', $thresholdTime)
                    ->get();

                foreach ($eligibleExecutions as $execution) {
                    $processedExecutionsCount++;

                    // Check if already triggered
                    $existingEvent = ProcessEscalationEvent::where('process_execution_id', $execution->id)
                        ->where('escalation_rule_id', $rule->id)
                        ->first();

                    if ($existingEvent) {
                        $alreadyTriggeredCount++;

                        continue;
                    }

                    // Attempt idempotent event creation
                    try {
                        $event = DB::transaction(function () use ($execution, $rule) {
                            return ProcessEscalationEvent::create([
                                'process_execution_id' => $execution->id,
                                'escalation_rule_id' => $rule->id,
                                'level' => $rule->level,
                                'triggered_at' => now(),
                                'status' => ProcessEscalationStatus::TRIGGERED,
                            ]);
                        });

                        $escalationsTriggeredCount++;

                        // Dispatch notification to recipient
                        try {
                            $recipient = $rule->escalateToUser;
                            if ($recipient) {
                                $recipient->notify(new ProcessEscalationNotification($process, $execution, $event));
                            }
                        } catch (\Throwable $notifEx) {
                            $notificationFailuresCount++;
                            Log::error("Failed sending escalation notification for event {$event->id}", [
                                'error' => $notifEx->getMessage(),
                            ]);
                        }
                    } catch (QueryException $e) {
                        // Unique constraint violation due to concurrent execution
                        $alreadyTriggeredCount++;
                    }
                }
            }
        }

        return [
            'processed_executions' => $processedExecutionsCount,
            'rules_evaluated' => $rulesEvaluatedCount,
            'escalations_triggered' => $escalationsTriggeredCount,
            'already_triggered' => $alreadyTriggeredCount,
            'skipped' => $skippedCount,
            'notification_failures' => $notificationFailuresCount,
        ];
    }

    /**
     * Acknowledge an escalation event.
     */
    public function acknowledgeEscalation(ProcessEscalationEvent $event, User $user): bool
    {
        if (! $event->isTriggered()) {
            return false;
        }

        $updated = (bool) $event->update([
            'status' => ProcessEscalationStatus::ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);

        if ($updated) {
            app(AuditLogService::class)->log(
                action: AuditAction::ACKNOWLEDGED,
                auditable: $event,
                beforeValues: ['status' => ProcessEscalationStatus::TRIGGERED->value],
                afterValues: ['status' => ProcessEscalationStatus::ACKNOWLEDGED->value, 'acknowledged_by' => $user->id],
                summary: "Escalation Level {$event->level} acknowledged by {$user->name}.",
                actor: $user
            );
        }

        return $updated;
    }

    /**
     * Automatically resolve all active escalations for a completed process execution.
     */
    public function resolveEscalationsForExecution(ProcessExecution $execution): int
    {
        return ProcessEscalationEvent::where('process_execution_id', $execution->id)
            ->whereIn('status', [ProcessEscalationStatus::TRIGGERED, ProcessEscalationStatus::ACKNOWLEDGED])
            ->update([
                'status' => ProcessEscalationStatus::RESOLVED,
            ]);
    }

    /**
     * Synchronize escalation rules for a process.
     *
     * @param  array<int, array{level: int, delay_minutes: int, escalate_to_user_id: int, is_active?: bool}>  $rulesData
     */
    public function syncRulesForProcess(Process $process, array $rulesData): void
    {
        DB::transaction(function () use ($process, $rulesData) {
            $existingRuleIds = [];

            // Sort by level and cap at 5 levels
            $cleanedRules = [];
            $seenLevels = [];

            foreach ($rulesData as $data) {
                $level = (int) ($data['level'] ?? 1);
                $delay = (int) ($data['delay_minutes'] ?? 30);
                $recipientId = (int) ($data['escalate_to_user_id'] ?? 0);
                $isActive = isset($data['is_active']) ? (bool) $data['is_active'] : true;

                if ($level < 1 || $level > 5 || $delay <= 0 || in_array($level, $seenLevels, true)) {
                    continue;
                }

                // Verify recipient is an active Manager or Admin
                $recipient = User::where('id', $recipientId)
                    ->whereIn('role', [UserRole::ADMIN, UserRole::MANAGER])
                    ->first();

                if (! $recipient) {
                    continue;
                }

                $seenLevels[] = $level;
                $cleanedRules[] = [
                    'level' => $level,
                    'delay_minutes' => $delay,
                    'escalate_to_user_id' => $recipient->id,
                    'is_active' => $isActive,
                ];
            }

            foreach ($cleanedRules as $ruleData) {
                $rule = ProcessEscalationRule::updateOrCreate(
                    [
                        'process_id' => $process->id,
                        'level' => $ruleData['level'],
                    ],
                    [
                        'delay_minutes' => $ruleData['delay_minutes'],
                        'escalate_to_user_id' => $ruleData['escalate_to_user_id'],
                        'is_active' => $ruleData['is_active'],
                    ]
                );
                $existingRuleIds[] = $rule->id;
            }

            // Remove deleted rules that are no longer present
            ProcessEscalationRule::where('process_id', $process->id)
                ->whereNotIn('id', $existingRuleIds)
                ->delete();
        });
    }
}
