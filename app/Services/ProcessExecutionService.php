<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ProcessExecutionStatus;
use App\Enums\ProcessItemStatus;
use App\Models\ProcessExecution;
use App\Models\ProcessExecutionItem;
use App\Models\TelegramEmployee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProcessExecutionService
{
    /**
     * Save progress on a checklist execution without confirming completion.
     *
     * @param  array<int, array{response?: ?string, notes?: ?string}>  $answers
     */
    public function saveProgress(ProcessExecution $execution, array $answers, User|TelegramEmployee $actor): ProcessExecution
    {
        return DB::transaction(function () use ($execution, $answers, $actor) {
            if ($execution->isCompleted()) {
                return $execution;
            }

            if ($execution->isPending()) {
                $execution->update([
                    'status' => ProcessExecutionStatus::IN_PROGRESS,
                    'started_at' => $execution->started_at ?? now(),
                ]);
            }

            $userId = ($actor instanceof User) ? $actor->id : null;
            $employeeId = ($actor instanceof TelegramEmployee) ? $actor->id : null;

            foreach ($answers as $itemId => $answerData) {
                $item = ProcessExecutionItem::where('process_execution_id', $execution->id)
                    ->where('id', $itemId)
                    ->first();

                if ($item) {
                    $hasResponse = isset($answerData['response']) && $answerData['response'] !== '' && $answerData['response'] !== null;
                    $item->update([
                        'response' => $answerData['response'] ?? null,
                        'notes' => $answerData['notes'] ?? null,
                        'answered_by' => $hasResponse ? $userId : $item->answered_by,
                        'answered_by_telegram_employee_id' => $hasResponse ? $employeeId : $item->answered_by_telegram_employee_id,
                        'answered_at' => $hasResponse ? now() : $item->answered_at,
                        'status' => $hasResponse ? ProcessItemStatus::ANSWERED : ProcessItemStatus::PENDING,
                    ]);
                }
            }

            return $execution->fresh(['items']);
        });
    }

    /**
     * Save a single item response (e.g. from Telegram interaction).
     */
    public function saveSingleItemResponse(ProcessExecutionItem $item, string $response, ?string $notes = null, User|TelegramEmployee|null $actor = null): ProcessExecutionItem
    {
        return DB::transaction(function () use ($item, $response, $notes, $actor) {
            $execution = $item->execution;
            if ($execution && $execution->isPending()) {
                $execution->update([
                    'status' => ProcessExecutionStatus::IN_PROGRESS,
                    'started_at' => $execution->started_at ?? now(),
                ]);
            }

            $userId = ($actor instanceof User) ? $actor->id : null;
            $employeeId = ($actor instanceof TelegramEmployee) ? $actor->id : null;

            $before = ['response' => $item->response, 'status' => $item->status->value ?? 'pending'];

            $item->update([
                'response' => $response,
                'notes' => $notes ?? $item->notes,
                'answered_by' => $userId,
                'answered_by_telegram_employee_id' => $employeeId,
                'answered_at' => now(),
                'status' => ProcessItemStatus::ANSWERED,
            ]);

            app(AuditLogService::class)->log(
                action: AuditAction::UPDATED,
                auditable: $item,
                beforeValues: $before,
                afterValues: ['response' => $response, 'status' => ProcessItemStatus::ANSWERED->value],
                summary: "Checklist item '{$item->question_snapshot}' answered: {$response}.",
                actor: $actor
            );

            return $item->fresh();
        });
    }

    /**
     * Validate and confirm completion of a checklist execution.
     *
     * @param  array<int, array{response?: ?string, notes?: ?string}>  $answers
     * @return array{success: bool, message: string, execution?: ProcessExecution}
     */
    public function confirmExecution(ProcessExecution $execution, array $answers, User|TelegramEmployee $actor): array
    {
        if ($execution->isCompleted()) {
            return [
                'success' => true,
                'message' => 'Checklist was already confirmed.',
                'execution' => $execution,
            ];
        }

        return DB::transaction(function () use ($execution, $answers, $actor) {
            $userId = ($actor instanceof User) ? $actor->id : null;
            $employeeId = ($actor instanceof TelegramEmployee) ? $actor->id : null;

            // First save answers
            foreach ($answers as $itemId => $answerData) {
                $item = ProcessExecutionItem::where('process_execution_id', $execution->id)
                    ->where('id', $itemId)
                    ->first();

                if ($item) {
                    $hasResponse = isset($answerData['response']) && $answerData['response'] !== '' && $answerData['response'] !== null;
                    $item->update([
                        'response' => $answerData['response'] ?? null,
                        'notes' => $answerData['notes'] ?? null,
                        'answered_by' => $hasResponse ? $userId : null,
                        'answered_by_telegram_employee_id' => $hasResponse ? $employeeId : null,
                        'answered_at' => $hasResponse ? now() : null,
                        'status' => $hasResponse ? ProcessItemStatus::ANSWERED : ProcessItemStatus::PENDING,
                    ]);
                }
            }

            // Check if any required items are unanswered
            $execution->load(['items.processItem']);
            foreach ($execution->items as $execItem) {
                $isRequired = $execItem->processItem ? $execItem->processItem->is_required : true;
                if ($isRequired && ! $execItem->isAnswered()) {
                    return [
                        'success' => false,
                        'message' => 'Please complete all required checklist items.',
                        'execution' => $execution,
                    ];
                }
            }

            // Mark as completed
            $execution->update([
                'status' => ProcessExecutionStatus::COMPLETED,
                'completed_by' => $userId,
                'completed_by_telegram_employee_id' => $employeeId,
                'completed_at' => now(),
                'started_at' => $execution->started_at ?? now(),
            ]);

            // Resolve any active escalation events
            app(ProcessEscalationService::class)->resolveEscalationsForExecution($execution);

            // Audit execution completion
            $actorSummary = ($actor instanceof TelegramEmployee)
                ? "Telegram-only Employee '{$actor->name}'"
                : "User '{$actor->name}'";

            app(AuditLogService::class)->log(
                action: AuditAction::COMPLETED,
                auditable: $execution,
                beforeValues: ['status' => ProcessExecutionStatus::PENDING->value],
                afterValues: [
                    'status' => ProcessExecutionStatus::COMPLETED->value,
                    'completed_by' => $userId,
                    'completed_by_telegram_employee_id' => $employeeId,
                ],
                summary: "Process checklist execution completed by {$actorSummary}.",
                actor: $actor
            );

            return [
                'success' => true,
                'message' => 'Checklist confirmed successfully.',
                'execution' => $execution->fresh(['items', 'completedBy', 'completedByTelegramEmployee', 'process']),
            ];
        });
    }
}
