<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TelegramEmployee;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private TelegramService $telegramService
    ) {}

    /**
     * Create a task.
     */
    public function createTask(array $data, ?User $creator = null): Task
    {
        return DB::transaction(function () use ($data, $creator) {
            $orgId = $data['organization_id'] ?? ($creator?->organization_id ?? 1);
            $code = ! empty($data['code']) ? $data['code'] : $this->generateTaskCode($orgId);

            $assignedTo = ! empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
            $assignedTelegramEmployeeId = ! empty($data['assigned_telegram_employee_id']) ? (int) $data['assigned_telegram_employee_id'] : null;

            $task = Task::create([
                'organization_id' => $orgId,
                'project_id' => $data['project_id'] ?? null,
                'code' => $code,
                'title' => trim($data['title']),
                'description' => $data['description'] ?? null,
                'assigned_to' => $assignedTo,
                'assigned_telegram_employee_id' => $assignedTelegramEmployeeId,
                'created_by' => $creator?->id ?? ($data['created_by'] ?? null),
                'priority' => isset($data['priority'])
                    ? ($data['priority'] instanceof TaskPriority ? $data['priority'] : TaskPriority::from($data['priority']))
                    : TaskPriority::MEDIUM,
                'status' => isset($data['status'])
                    ? ($data['status'] instanceof TaskStatus ? $data['status'] : TaskStatus::from($data['status']))
                    : TaskStatus::PENDING,
                'due_date' => ! empty($data['due_date']) ? Carbon::parse($data['due_date']) : null,
                'recurring_task_id' => $data['recurring_task_id'] ?? null,
            ]);

            $this->auditLogService->log(
                action: AuditAction::CREATED,
                auditable: $task,
                beforeValues: null,
                afterValues: $task->toArray(),
                summary: "Task '{$task->title}' ({$task->code}) created."
            );

            // Send assignment notification
            $this->sendAssignmentNotification($task);

            return $task;
        });
    }

    /**
     * Update an existing task.
     */
    public function updateTask(Task $task, array $data): Task
    {
        return DB::transaction(function () use ($task, $data) {
            $before = $task->toArray();
            $oldAssigneeUser = $task->assigned_to;
            $oldAssigneeEmployee = $task->assigned_telegram_employee_id;

            $assignedTo = array_key_exists('assigned_to', $data)
                ? (! empty($data['assigned_to']) ? (int) $data['assigned_to'] : null)
                : $task->assigned_to;

            $assignedTelegramEmployeeId = array_key_exists('assigned_telegram_employee_id', $data)
                ? (! empty($data['assigned_telegram_employee_id']) ? (int) $data['assigned_telegram_employee_id'] : null)
                : $task->assigned_telegram_employee_id;

            $status = isset($data['status'])
                ? ($data['status'] instanceof TaskStatus ? $data['status'] : TaskStatus::from($data['status']))
                : $task->status;

            $priority = isset($data['priority'])
                ? ($data['priority'] instanceof TaskPriority ? $data['priority'] : TaskPriority::from($data['priority']))
                : $task->priority;

            $completedAt = $task->completed_at;
            if ($status === TaskStatus::COMPLETED && ! $task->completed_at) {
                $completedAt = now();
            } elseif ($status !== TaskStatus::COMPLETED) {
                $completedAt = null;
            }

            $task->update([
                'project_id' => array_key_exists('project_id', $data) ? $data['project_id'] : $task->project_id,
                'title' => isset($data['title']) ? trim($data['title']) : $task->title,
                'description' => array_key_exists('description', $data) ? $data['description'] : $task->description,
                'assigned_to' => $assignedTo,
                'assigned_telegram_employee_id' => $assignedTelegramEmployeeId,
                'priority' => $priority,
                'status' => $status,
                'due_date' => array_key_exists('due_date', $data) ? (! empty($data['due_date']) ? Carbon::parse($data['due_date']) : null) : $task->due_date,
                'completed_at' => $completedAt,
            ]);

            $this->auditLogService->log(
                action: AuditAction::UPDATED,
                auditable: $task,
                beforeValues: $before,
                afterValues: $task->fresh()->toArray(),
                summary: "Task '{$task->title}' ({$task->code}) updated."
            );

            // Reassignment check
            $reassigned = ($assignedTo !== $oldAssigneeUser) || ($assignedTelegramEmployeeId !== $oldAssigneeEmployee);
            if ($reassigned) {
                $this->sendAssignmentNotification($task, isReassignment: true);
            }

            return $task;
        });
    }

    /**
     * Complete a task.
     */
    public function completeTask(Task $task, ?User $completedBy = null, ?TelegramEmployee $completedByEmployee = null): Task
    {
        return DB::transaction(function () use ($task, $completedBy, $completedByEmployee) {
            $before = ['status' => $task->status->value, 'completed_at' => $task->completed_at];

            $task->update([
                'status' => TaskStatus::COMPLETED,
                'completed_at' => now(),
            ]);

            $actorName = $completedByEmployee ? "Telegram Employee '{$completedByEmployee->name}'" : ($completedBy ? "User '{$completedBy->name}'" : 'System');

            $this->auditLogService->log(
                action: AuditAction::COMPLETED,
                auditable: $task,
                beforeValues: $before,
                afterValues: ['status' => TaskStatus::COMPLETED->value, 'completed_at' => $task->completed_at->toIso8601String()],
                summary: "Task '{$task->title}' ({$task->code}) marked as completed by {$actorName}.",
                actor: $completedByEmployee ?? $completedBy
            );

            return $task;
        });
    }

    /**
     * Send assignment notification to Web User or Telegram Employee.
     */
    public function sendAssignmentNotification(Task $task, bool $isReassignment = false): void
    {
        try {
            if ($task->assigned_to && $task->assignee) {
                $task->assignee->notify(new TaskAssignedNotification($task, isReassignment: $isReassignment));
            } elseif ($task->assigned_telegram_employee_id && $task->assignedTelegramEmployee) {
                $employee = $task->assignedTelegramEmployee;
                if ($employee->isTelegramConnected() && $employee->telegramAccount->chat_id) {
                    $actionText = $isReassignment ? 'Task Reassigned' : 'New Task Assigned';
                    $dueText = $task->due_date ? $task->due_date->format('d M Y, h:i A') : 'No due date';
                    $priority = ucfirst($task->priority->value ?? 'medium');

                    $msg = "📋 <b>{$actionText}</b>\n\n";
                    $msg .= "<b>Title:</b> {$task->title}\n";
                    $msg .= "<b>Code:</b> {$task->code}\n";
                    $msg .= "<b>Priority:</b> {$priority}\n";
                    $msg .= "<b>Due:</b> {$dueText}\n\n";
                    $msg .= "To complete this task, reply:\n<code>DONE {$task->code}</code>";

                    $this->telegramService->sendMessage($employee->telegramAccount->chat_id, $msg);
                }
            }
        } catch (\Throwable $e) {
            Log::error('TaskService: Failed to send assignment notification', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate unique task code for organization.
     */
    public function generateTaskCode(int $organizationId): string
    {
        $count = Task::where('organization_id', $organizationId)->count() + 1;
        $code = 'TSK-'.str_pad((string) $count, 4, '0', STR_PAD_LEFT);

        $counter = $count + 1;
        while (Task::where('organization_id', $organizationId)->where('code', $code)->exists()) {
            $code = 'TSK-'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }
}
