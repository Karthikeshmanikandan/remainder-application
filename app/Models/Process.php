<?php

namespace App\Models;

use App\Enums\ProcessFrequency;
use App\Enums\ProcessStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'department_id',
        'process_template_id',
        'name',
        'code',
        'description',
        'responsible_user_id',
        'responsible_telegram_employee_id',
        'frequency',
        'interval',
        'reminder_enabled',
        'reminder_time',
        'next_run_at',
        'last_run_at',
        'telegram_enabled',
        'in_app_enabled',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'frequency' => ProcessFrequency::class,
        'status' => ProcessStatus::class,
        'interval' => 'integer',
        'reminder_enabled' => 'boolean',
        'telegram_enabled' => 'boolean',
        'in_app_enabled' => 'boolean',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function processTemplate(): BelongsTo
    {
        return $this->belongsTo(ProcessTemplate::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibleTelegramEmployee(): BelongsTo
    {
        return $this->belongsTo(TelegramEmployee::class, 'responsible_telegram_employee_id');
    }

    public function responsiblePerson(): User|TelegramEmployee|null
    {
        return $this->responsibleUser ?? $this->responsibleTelegramEmployee;
    }

    public function getResponsibleNameAttribute(): string
    {
        return $this->responsiblePerson()?->name ?? 'Unassigned';
    }

    public function getResponsibleTypeLabelAttribute(): string
    {
        if ($this->responsible_telegram_employee_id) {
            return 'Telegram Employee';
        }
        if ($this->responsible_user_id) {
            return 'Web User';
        }

        return 'Unassigned';
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcessItem::class)->orderBy('sort_order');
    }

    public function enabledItems(): HasMany
    {
        return $this->hasMany(ProcessItem::class)->where('is_enabled', true)->orderBy('sort_order');
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ProcessExecution::class);
    }

    public function escalationRules(): HasMany
    {
        return $this->hasMany(ProcessEscalationRule::class)->orderBy('level');
    }

    public function activeEscalationRules(): HasMany
    {
        return $this->hasMany(ProcessEscalationRule::class)->where('is_active', true)->orderBy('level');
    }

    public function hasEscalationEnabled(): bool
    {
        return $this->activeEscalationRules()->exists();
    }

    public function isActive(): bool
    {
        return $this->status === ProcessStatus::ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === ProcessStatus::PAUSED;
    }

    public function isCompleted(): bool
    {
        return $this->status === ProcessStatus::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProcessStatus::CANCELLED;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->next_run_at !== null && $this->next_run_at->gt($this->ends_at);
    }
}
