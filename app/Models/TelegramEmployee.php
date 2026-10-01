<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TelegramEmployee extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'department_id',
        'employee_code',
        'name',
        'email',
        'phone',
        'is_active',
        'telegram_username',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function telegramAccount(): HasOne
    {
        return $this->hasOne(TelegramAccount::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_telegram_employee_id');
    }

    public function assignedProcesses(): HasMany
    {
        return $this->hasMany(Process::class, 'responsible_telegram_employee_id');
    }

    public function completedProcessExecutions(): HasMany
    {
        return $this->hasMany(ProcessExecution::class, 'completed_by_telegram_employee_id');
    }

    public function answeredProcessExecutionItems(): HasMany
    {
        return $this->hasMany(ProcessExecutionItem::class, 'answered_by_telegram_employee_id');
    }

    public function isTelegramConnected(): bool
    {
        return $this->telegramAccount && $this->telegramAccount->isVerified();
    }

    public function hasActiveTelegram(): bool
    {
        return $this->telegramAccount && $this->telegramAccount->isVerified() && $this->telegramAccount->is_active;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }
}
