<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Organization;
use App\Models\TelegramEmployee;
use Illuminate\Support\Facades\DB;

class TelegramEmployeeService
{
    public function __construct(
        private AuditLogService $auditLogService,
        private TelegramAccountService $telegramAccountService
    ) {}

    /**
     * Create a new Telegram-only employee.
     *
     * @param  array{organization_id: int, name: string, employee_code?: string, email?: ?string, phone?: ?string, department_id?: ?int, is_active?: bool}  $data
     */
    public function createEmployee(array $data): TelegramEmployee
    {
        return DB::transaction(function () use ($data) {
            $orgId = (int) $data['organization_id'];
            $code = ! empty($data['employee_code'])
                ? strtoupper(trim($data['employee_code']))
                : $this->generateUniqueEmployeeCode($orgId);

            $employee = TelegramEmployee::create([
                'organization_id' => $orgId,
                'department_id' => $data['department_id'] ?? null,
                'employee_code' => $code,
                'name' => trim($data['name']),
                'email' => ! empty($data['email']) ? trim($data['email']) : null,
                'phone' => ! empty($data['phone']) ? trim($data['phone']) : null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'telegram_username' => $data['telegram_username'] ?? null,
            ]);

            $this->auditLogService->log(
                action: AuditAction::CREATED,
                auditable: $employee,
                beforeValues: null,
                afterValues: $employee->toArray(),
                summary: "Telegram-only Employee '{$employee->name}' ({$employee->employee_code}) created."
            );

            return $employee;
        });
    }

    /**
     * Update an existing Telegram-only employee.
     *
     * @param  array{name?: string, email?: ?string, phone?: ?string, department_id?: ?int, is_active?: bool}  $data
     */
    public function updateEmployee(TelegramEmployee $employee, array $data): TelegramEmployee
    {
        return DB::transaction(function () use ($employee, $data) {
            $before = $employee->toArray();

            $employee->update([
                'name' => isset($data['name']) ? trim($data['name']) : $employee->name,
                'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : $employee->department_id,
                'email' => array_key_exists('email', $data) ? $data['email'] : $employee->email,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $employee->phone,
                'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $employee->is_active,
            ]);

            $this->auditLogService->log(
                action: AuditAction::UPDATED,
                auditable: $employee,
                beforeValues: $before,
                afterValues: $employee->fresh()->toArray(),
                summary: "Telegram-only Employee '{$employee->name}' ({$employee->employee_code}) updated."
            );

            return $employee;
        });
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(TelegramEmployee $employee): TelegramEmployee
    {
        return DB::transaction(function () use ($employee) {
            $newStatus = ! $employee->is_active;
            $action = $newStatus ? AuditAction::RESUMED : AuditAction::PAUSED;
            $actionText = $newStatus ? 'activated' : 'deactivated';

            $before = ['is_active' => $employee->is_active];
            $employee->update(['is_active' => $newStatus]);
            $after = ['is_active' => $newStatus];

            $this->auditLogService->log(
                action: $action,
                auditable: $employee,
                beforeValues: $before,
                afterValues: $after,
                summary: "Telegram-only Employee '{$employee->name}' ({$employee->employee_code}) {$actionText}."
            );

            return $employee;
        });
    }

    /**
     * Generate a new short-lived verification code for Telegram linking.
     */
    public function generateVerificationCode(TelegramEmployee $employee): string
    {
        return $this->telegramAccountService->generateCodeForTelegramEmployee($employee);
    }

    /**
     * Unlink Telegram account.
     */
    public function unlinkAccount(TelegramEmployee $employee): void
    {
        $this->telegramAccountService->unlinkEmployeeAccount($employee);
    }

    /**
     * Generate unique employee code within organization.
     */
    public function generateUniqueEmployeeCode(int $organizationId): string
    {
        $count = TelegramEmployee::where('organization_id', $organizationId)->count() + 1;
        $code = 'EMP-'.str_pad((string) $count, 3, '0', STR_PAD_LEFT);

        $counter = $count + 1;
        while (TelegramEmployee::where('organization_id', $organizationId)->where('employee_code', $code)->exists()) {
            $code = 'EMP-'.str_pad((string) $counter, 3, '0', STR_PAD_LEFT);
            $counter++;
        }

        return $code;
    }
}
