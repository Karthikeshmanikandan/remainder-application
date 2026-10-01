<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\ProcessFrequency;
use App\Enums\ProcessResponseType;
use App\Enums\ProcessStatus;
use App\Models\Department;
use App\Models\Process;
use App\Models\ProcessItem;
use App\Models\ProcessTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProcessService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Create a new process definition from a template or custom configuration.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $itemsData
     * @param  array<int, array<string, mixed>>  $escalationRulesData
     */
    public function createProcess(array $data, array $itemsData = [], array $escalationRulesData = []): Process
    {
        return DB::transaction(function () use ($data, $itemsData, $escalationRulesData) {
            $department = Department::find($data['department_id']);
            $orgId = $data['organization_id'] ?? ($department?->organization_id ?? 1);
            $code = $data['code'] ?? $this->generateProcessCode($data['name']);

            $frequency = $data['frequency'] instanceof ProcessFrequency
                ? $data['frequency']
                : ProcessFrequency::from($data['frequency']);

            $interval = (int) ($data['interval'] ?? 1);
            $reminderTime = $data['reminder_time'] ?? '17:00';
            $startsAt = isset($data['starts_at']) && $data['starts_at'] ? Carbon::parse($data['starts_at']) : now();

            $initialNextRun = $this->calculateInitialNextRun($startsAt, $reminderTime);

            $responsibleUserId = ! empty($data['responsible_user_id']) ? (int) $data['responsible_user_id'] : null;
            $responsibleTelegramEmployeeId = ! empty($data['responsible_telegram_employee_id']) ? (int) $data['responsible_telegram_employee_id'] : null;

            $process = Process::create([
                'organization_id' => $orgId,
                'department_id' => $data['department_id'],
                'process_template_id' => $data['process_template_id'] ?? null,
                'name' => $data['name'],
                'code' => $code,
                'description' => $data['description'] ?? null,
                'responsible_user_id' => $responsibleUserId,
                'responsible_telegram_employee_id' => $responsibleTelegramEmployeeId,
                'frequency' => $frequency,
                'interval' => $interval,
                'reminder_enabled' => (bool) ($data['reminder_enabled'] ?? true),
                'reminder_time' => $reminderTime,
                'next_run_at' => $initialNextRun,
                'last_run_at' => null,
                'telegram_enabled' => (bool) ($data['telegram_enabled'] ?? false),
                'in_app_enabled' => (bool) ($data['in_app_enabled'] ?? true),
                'status' => ProcessStatus::ACTIVE,
                'starts_at' => $startsAt,
                'ends_at' => isset($data['ends_at']) && $data['ends_at'] ? Carbon::parse($data['ends_at']) : null,
            ]);

            // If template provided and no custom items provided, copy active template items
            if (! empty($itemsData)) {
                foreach ($itemsData as $index => $item) {
                    ProcessItem::create([
                        'process_id' => $process->id,
                        'template_item_id' => $item['template_item_id'] ?? null,
                        'question' => $item['question'],
                        'description' => $item['description'] ?? null,
                        'response_type' => isset($item['response_type'])
                            ? ($item['response_type'] instanceof ProcessResponseType ? $item['response_type'] : ProcessResponseType::from($item['response_type']))
                            : ProcessResponseType::YES_NO,
                        'sort_order' => $item['sort_order'] ?? ($index + 1),
                        'is_required' => (bool) ($item['is_required'] ?? true),
                        'is_enabled' => (bool) ($item['is_enabled'] ?? true),
                    ]);
                }
            } elseif ($process->process_template_id) {
                $template = ProcessTemplate::with('items')->find($process->process_template_id);
                if ($template) {
                    foreach ($template->items()->where('is_active', true)->orderBy('sort_order')->get() as $tItem) {
                        ProcessItem::create([
                            'process_id' => $process->id,
                            'template_item_id' => $tItem->id,
                            'question' => $tItem->question,
                            'description' => $tItem->description,
                            'response_type' => $tItem->response_type,
                            'sort_order' => $tItem->sort_order,
                            'is_required' => $tItem->is_required,
                            'is_enabled' => true,
                        ]);
                    }
                }
            }

            // Sync escalation rules if provided
            if (! empty($escalationRulesData)) {
                app(ProcessEscalationService::class)->syncRulesForProcess($process, $escalationRulesData);
            }

            // Audit process creation
            $this->auditLogService->log(
                action: AuditAction::CREATED,
                auditable: $process,
                beforeValues: null,
                afterValues: [
                    'name' => $process->name,
                    'code' => $process->code,
                    'department_id' => $process->department_id,
                    'responsible_user_id' => $process->responsible_user_id,
                    'responsible_telegram_employee_id' => $process->responsible_telegram_employee_id,
                    'frequency' => $process->frequency->value,
                    'reminder_time' => $process->reminder_time,
                    'status' => $process->status->value,
                ],
                summary: "Process '{$process->name}' ({$process->code}) created."
            );

            return $process;
        });
    }

    /**
     * Update an existing process definition.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $itemsData
     * @param  array<int, array<string, mixed>>  $escalationRulesData
     */
    public function updateProcess(Process $process, array $data, array $itemsData = [], array $escalationRulesData = []): Process
    {
        return DB::transaction(function () use ($process, $data, $itemsData, $escalationRulesData) {
            $original = [
                'name' => $process->name,
                'code' => $process->code,
                'description' => $process->description,
                'responsible_user_id' => $process->responsible_user_id,
                'responsible_telegram_employee_id' => $process->responsible_telegram_employee_id,
                'frequency' => $process->frequency->value,
                'interval' => $process->interval,
                'reminder_enabled' => $process->reminder_enabled,
                'reminder_time' => $process->reminder_time,
                'telegram_enabled' => $process->telegram_enabled,
                'in_app_enabled' => $process->in_app_enabled,
                'ends_at' => $process->ends_at?->toIso8601String(),
            ];
            $oldResponsibleUserId = $process->responsible_user_id;
            $oldResponsibleEmployeeId = $process->responsible_telegram_employee_id;
            $oldResponsibleName = $process->responsible_name;

            $frequency = isset($data['frequency'])
                ? ($data['frequency'] instanceof ProcessFrequency ? $data['frequency'] : ProcessFrequency::from($data['frequency']))
                : $process->frequency;

            $responsibleUserId = array_key_exists('responsible_user_id', $data)
                ? (! empty($data['responsible_user_id']) ? (int) $data['responsible_user_id'] : null)
                : $process->responsible_user_id;

            $responsibleEmployeeId = array_key_exists('responsible_telegram_employee_id', $data)
                ? (! empty($data['responsible_telegram_employee_id']) ? (int) $data['responsible_telegram_employee_id'] : null)
                : $process->responsible_telegram_employee_id;

            $process->update([
                'name' => $data['name'] ?? $process->name,
                'code' => $data['code'] ?? $process->code,
                'description' => $data['description'] ?? $process->description,
                'responsible_user_id' => $responsibleUserId,
                'responsible_telegram_employee_id' => $responsibleEmployeeId,
                'frequency' => $frequency,
                'interval' => isset($data['interval']) ? (int) $data['interval'] : $process->interval,
                'reminder_enabled' => isset($data['reminder_enabled']) ? (bool) $data['reminder_enabled'] : $process->reminder_enabled,
                'reminder_time' => $data['reminder_time'] ?? $process->reminder_time,
                'telegram_enabled' => isset($data['telegram_enabled']) ? (bool) $data['telegram_enabled'] : $process->telegram_enabled,
                'in_app_enabled' => isset($data['in_app_enabled']) ? (bool) $data['in_app_enabled'] : $process->in_app_enabled,
                'ends_at' => isset($data['ends_at']) && $data['ends_at'] ? Carbon::parse($data['ends_at']) : $process->ends_at,
            ]);

            // Audit Reassignment if responsible user / employee changed
            $reassigned = ($oldResponsibleUserId != $process->responsible_user_id) || ($oldResponsibleEmployeeId != $process->responsible_telegram_employee_id);
            if ($reassigned) {
                $newResponsibleName = $process->fresh()->responsible_name;
                $this->auditLogService->log(
                    action: AuditAction::REASSIGNED,
                    auditable: $process,
                    beforeValues: ['responsible_user_id' => $oldResponsibleUserId, 'responsible_telegram_employee_id' => $oldResponsibleEmployeeId, 'responsible' => $oldResponsibleName],
                    afterValues: ['responsible_user_id' => $process->responsible_user_id, 'responsible_telegram_employee_id' => $process->responsible_telegram_employee_id, 'responsible' => $newResponsibleName],
                    summary: "Responsible user changed from {$oldResponsibleName} to {$newResponsibleName}."
                );
            }

            // Audit other field updates
            $newValues = [
                'name' => $process->name,
                'code' => $process->code,
                'description' => $process->description,
                'frequency' => $process->frequency->value,
                'interval' => $process->interval,
                'reminder_enabled' => $process->reminder_enabled,
                'reminder_time' => $process->reminder_time,
                'telegram_enabled' => $process->telegram_enabled,
                'in_app_enabled' => $process->in_app_enabled,
                'ends_at' => $process->ends_at?->toIso8601String(),
            ];

            $diffBefore = [];
            $diffAfter = [];
            foreach ($newValues as $key => $val) {
                if ($original[$key] != $val) {
                    $diffBefore[$key] = $original[$key];
                    $diffAfter[$key] = $val;
                }
            }

            if (! empty($diffBefore)) {
                $changes = [];
                foreach ($diffBefore as $k => $oldV) {
                    $newV = $diffAfter[$k];
                    $changes[] = "{$k} changed from {$oldV} to {$newV}";
                }
                $this->auditLogService->log(
                    action: AuditAction::UPDATED,
                    auditable: $process,
                    beforeValues: $diffBefore,
                    afterValues: $diffAfter,
                    summary: 'Process updated: '.implode(', ', $changes).'.'
                );
            }

            // If items update specified (e.g. toggling questions)
            if (! empty($itemsData)) {
                $itemsBefore = [];
                $itemsAfter = [];
                foreach ($itemsData as $itemId => $itemAttrs) {
                    $pItem = ProcessItem::where('process_id', $process->id)->where('id', $itemId)->first();
                    if ($pItem) {
                        $itemsBefore[$pItem->id] = [
                            'question' => $pItem->question,
                            'is_enabled' => $pItem->is_enabled,
                            'is_required' => $pItem->is_required,
                        ];

                        $pItem->update([
                            'is_enabled' => (bool) ($itemAttrs['is_enabled'] ?? false),
                            'is_required' => (bool) ($itemAttrs['is_required'] ?? $pItem->is_required),
                        ]);

                        $itemsAfter[$pItem->id] = [
                            'question' => $pItem->question,
                            'is_enabled' => $pItem->is_enabled,
                            'is_required' => $pItem->is_required,
                        ];
                    }
                }

                $this->auditLogService->log(
                    action: AuditAction::CONFIGURED,
                    auditable: $process,
                    beforeValues: $itemsBefore,
                    afterValues: $itemsAfter,
                    summary: 'Process checklist questions configuration updated.'
                );
            }

            // Sync escalation rules if provided
            if (! empty($escalationRulesData)) {
                app(ProcessEscalationService::class)->syncRulesForProcess($process, $escalationRulesData);
            }

            return $process;
        });
    }

    /**
     * Pause an active process definition and audit the action.
     */
    public function pauseProcess(Process $process): void
    {
        DB::transaction(function () use ($process) {
            $oldStatus = $process->status->value;
            $process->update(['status' => ProcessStatus::PAUSED]);

            $this->auditLogService->log(
                action: AuditAction::PAUSED,
                auditable: $process,
                beforeValues: ['status' => $oldStatus],
                afterValues: ['status' => ProcessStatus::PAUSED->value],
                summary: "Process '{$process->name}' paused."
            );
        });
    }

    /**
     * Resume a paused process definition and audit the action.
     */
    public function resumeProcess(Process $process): void
    {
        DB::transaction(function () use ($process) {
            $oldStatus = $process->status->value;
            $process->update(['status' => ProcessStatus::ACTIVE]);

            $this->auditLogService->log(
                action: AuditAction::RESUMED,
                auditable: $process,
                beforeValues: ['status' => $oldStatus],
                afterValues: ['status' => ProcessStatus::ACTIVE->value],
                summary: "Process '{$process->name}' resumed."
            );
        });
    }

    /**
     * Cancel a process definition and audit the action.
     */
    public function cancelProcess(Process $process): void
    {
        DB::transaction(function () use ($process) {
            $oldStatus = $process->status->value;
            $process->update(['status' => ProcessStatus::CANCELLED]);

            $this->auditLogService->log(
                action: AuditAction::CANCELLED,
                auditable: $process,
                beforeValues: ['status' => $oldStatus],
                afterValues: ['status' => ProcessStatus::CANCELLED->value],
                summary: "Process '{$process->name}' cancelled."
            );
        });
    }

    /**
     * Calculate initial next run based on starts_at and reminder_time.
     */
    public function calculateInitialNextRun(Carbon $startsAt, ?string $reminderTime = '17:00'): Carbon
    {
        $next = $startsAt->copy();
        if ($reminderTime && preg_match('/^(\d{1,2}):(\d{2})$/', $reminderTime, $matches)) {
            $next->setTime((int) $matches[1], (int) $matches[2], 0);
        }

        return $next;
    }

    /**
     * Calculate next run datetime with clamped month-end calculation.
     */
    public function calculateNextRun(ProcessFrequency $frequency, Carbon $from, int $interval = 1, ?string $reminderTime = null): Carbon
    {
        $next = $from->copy();

        $next = match ($frequency) {
            ProcessFrequency::DAILY => $next->addDays($interval),
            ProcessFrequency::WEEKLY => $next->addWeeks($interval),
            ProcessFrequency::MONTHLY => $next->addMonthsNoOverflow($interval),
        };

        if ($reminderTime && preg_match('/^(\d{1,2}):(\d{2})$/', $reminderTime, $matches)) {
            $next->setTime((int) $matches[1], (int) $matches[2], 0);
        }

        return $next;
    }

    /**
     * Generate unique process code.
     */
    private function generateProcessCode(string $name): string
    {
        $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));
        if (strlen($slug) < 3) {
            $slug = 'PROC';
        }

        $base = "PRC-{$slug}-".rand(100, 999);
        $code = $base;
        $counter = 1;
        while (Process::where('code', $code)->exists()) {
            $code = "{$base}-{$counter}";
            $counter++;
        }

        return $code;
    }
}
