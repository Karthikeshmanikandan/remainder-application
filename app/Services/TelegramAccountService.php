<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\TelegramAccount;
use App\Models\TelegramEmployee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TelegramAccountService
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    /**
     * Generate a new verification code for the web user.
     */
    public function generateVerificationCode(User $user): string
    {
        $account = $user->telegramAccount()->firstOrCreate(['user_id' => $user->id]);

        $code = 'TG-'.strtoupper(Str::random(6));

        $account->update([
            'verification_code' => $code,
            'verification_expires_at' => now()->addMinutes(15),
        ]);

        $this->auditLogService->log(
            action: AuditAction::UPDATED,
            auditable: $user,
            beforeValues: null,
            afterValues: ['verification_code' => $code],
            summary: "Telegram linking code generated for Web User '{$user->name}' ({$user->email})."
        );

        return $code;
    }

    /**
     * Generate a new verification code for the telegram-only employee.
     */
    public function generateCodeForTelegramEmployee(TelegramEmployee $employee): string
    {
        $account = $employee->telegramAccount()->firstOrCreate(['telegram_employee_id' => $employee->id]);

        $code = 'TGEMP-'.strtoupper(Str::random(6));

        $account->update([
            'verification_code' => $code,
            'verification_expires_at' => now()->addMinutes(15),
        ]);

        $this->auditLogService->log(
            action: AuditAction::UPDATED,
            auditable: $employee,
            beforeValues: null,
            afterValues: ['verification_code' => $code],
            summary: "Telegram linking code generated for Employee '{$employee->name}' ({$employee->employee_code})."
        );

        return $code;
    }

    /**
     * Verify a code and link the account to the chat.
     */
    public function verifyCodeAndLink(string $code, string $chatId, ?string $telegramUserId = null, ?string $telegramUsername = null): ?TelegramAccount
    {
        return DB::transaction(function () use ($code, $chatId, $telegramUserId, $telegramUsername) {
            $account = TelegramAccount::where('verification_code', $code)
                ->where('verification_expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $account) {
                return null;
            }

            // Check if this chat_id or telegram_user_id is already linked to another account
            $existing = TelegramAccount::where(function ($query) use ($chatId, $telegramUserId) {
                $query->where('chat_id', $chatId);
                if ($telegramUserId) {
                    $query->orWhere('telegram_user_id', $telegramUserId);
                }
            })->where('id', '!=', $account->id)
                ->whereNotNull('verified_at')
                ->first();

            if ($existing) {
                // Cannot link one chat to multiple accounts
                return null;
            }

            // If it's an employee account, ensure employee and organization are active
            if ($account->telegram_employee_id) {
                $employee = TelegramEmployee::with('organization')->find($account->telegram_employee_id);
                if (! $employee || ! $employee->is_active) {
                    return null;
                }
                if ($employee->organization && ! $employee->organization->is_active) {
                    return null;
                }

                if ($telegramUsername) {
                    $employee->update(['telegram_username' => $telegramUsername]);
                }

                $account->update([
                    'chat_id' => $chatId,
                    'telegram_user_id' => $telegramUserId,
                    'telegram_username' => $telegramUsername,
                    'verification_code' => null,
                    'verification_expires_at' => null,
                    'verified_at' => now(),
                    'is_active' => true,
                    'last_seen_at' => now(),
                ]);

                $this->auditLogService->log(
                    action: AuditAction::UPDATED,
                    auditable: $employee,
                    beforeValues: ['is_telegram_connected' => false],
                    afterValues: [
                        'is_telegram_connected' => true,
                        'telegram_username' => $telegramUsername,
                    ],
                    summary: "Telegram-only Employee '{$employee->name}' ({$employee->employee_code}) successfully linked to Telegram."
                );

                return $account;
            }

            // If it's a web user account, ensure user and organization are active
            if ($account->user_id) {
                $user = User::with('organization')->find($account->user_id);
                if (! $user || ($user->status && $user->status->value === 'inactive')) {
                    return null;
                }
                if ($user->organization && ! $user->organization->is_active) {
                    return null;
                }

                $account->update([
                    'chat_id' => $chatId,
                    'telegram_user_id' => $telegramUserId,
                    'telegram_username' => $telegramUsername,
                    'verification_code' => null,
                    'verification_expires_at' => null,
                    'verified_at' => now(),
                    'is_active' => true,
                    'last_seen_at' => now(),
                ]);

                // Set default notification preference
                $preference = $user->notificationPreference()->firstOrCreate(['user_id' => $user->id]);
                $preference->update(['telegram_enabled' => true]);

                $this->auditLogService->log(
                    action: AuditAction::UPDATED,
                    auditable: $user,
                    beforeValues: ['is_telegram_connected' => false],
                    afterValues: [
                        'is_telegram_connected' => true,
                        'telegram_username' => $telegramUsername,
                    ],
                    summary: "Web User '{$user->name}' ({$user->email}) successfully linked to Telegram."
                );

                return $account;
            }

            return null;
        });
    }

    /**
     * Unlink the user's Telegram account.
     */
    public function unlinkAccount(User $user): void
    {
        if ($user->telegramAccount) {
            $user->telegramAccount->update([
                'chat_id' => null,
                'telegram_user_id' => null,
                'telegram_username' => null,
                'verification_code' => null,
                'verification_expires_at' => null,
                'verified_at' => null,
                'is_active' => false,
            ]);

            if ($user->notificationPreference) {
                $user->notificationPreference->update(['telegram_enabled' => false]);
            }

            $this->auditLogService->log(
                action: AuditAction::UPDATED,
                auditable: $user,
                beforeValues: ['is_telegram_connected' => true],
                afterValues: ['is_telegram_connected' => false],
                summary: "Telegram account unlinked for Web User '{$user->name}' ({$user->email})."
            );
        }
    }

    /**
     * Unlink an employee's Telegram account.
     */
    public function unlinkEmployeeAccount(TelegramEmployee $employee): void
    {
        if ($employee->telegramAccount) {
            $employee->telegramAccount->update([
                'chat_id' => null,
                'telegram_user_id' => null,
                'telegram_username' => null,
                'verification_code' => null,
                'verification_expires_at' => null,
                'verified_at' => null,
                'is_active' => false,
            ]);

            $employee->update(['telegram_username' => null]);

            $this->auditLogService->log(
                action: AuditAction::UPDATED,
                auditable: $employee,
                beforeValues: ['is_telegram_connected' => true],
                afterValues: ['is_telegram_connected' => false],
                summary: "Telegram account unlinked for Employee '{$employee->name}' ({$employee->employee_code})."
            );
        }
    }
}
