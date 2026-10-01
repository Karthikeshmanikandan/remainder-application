<?php

namespace App\Models;

use App\Enums\ProcessExecutionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'process_id',
        'occurrence_key',
        'scheduled_for',
        'started_at',
        'completed_at',
        'status',
        'completed_by',
        'completed_by_telegram_employee_id',
        'responsible_name_snapshot',
        'responsible_type_snapshot',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => ProcessExecutionStatus::class,
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function completedByTelegramEmployee(): BelongsTo
    {
        return $this->belongsTo(TelegramEmployee::class, 'completed_by_telegram_employee_id');
    }

    public function completedByPerson(): User|TelegramEmployee|null
    {
        return $this->completedBy ?? $this->completedByTelegramEmployee;
    }

    public function getCompletedByNameAttribute(): ?string
    {
        return $this->completedByPerson()?->name;
    }

    public function getResponsibleNameAttribute(): string
    {
        if ($this->responsible_name_snapshot) {
            return $this->responsible_name_snapshot;
        }

        return $this->process?->responsible_name ?? 'Unassigned';
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcessExecutionItem::class);
    }

    public function escalationEvents(): HasMany
    {
        return $this->hasMany(ProcessEscalationEvent::class, 'process_execution_id')->orderBy('level');
    }

    public function isCompleted(): bool
    {
        return $this->status === ProcessExecutionStatus::COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === ProcessExecutionStatus::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === ProcessExecutionStatus::IN_PROGRESS;
    }

    public function isMissed(): bool
    {
        return $this->status === ProcessExecutionStatus::MISSED;
    }

    public function isOverdue(): bool
    {
        if ($this->isCompleted() || $this->isMissed()) {
            return false;
        }

        return $this->scheduled_for->isPast();
    }

    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', [ProcessExecutionStatus::COMPLETED, ProcessExecutionStatus::MISSED])
            ->where('scheduled_for', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('scheduled_for', today());
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        if ($startDate && $endDate) {
            return $query->whereBetween('scheduled_for', [$startDate, $endDate]);
        }

        return $query;
    }

    public function scopeForDepartment($query, $departmentId)
    {
        return $query->whereHas('process', function ($q) use ($departmentId) {
            $q->where('department_id', $departmentId);
        });
    }

    public function scopeForUser($query, $userId)
    {
        return $query->whereHas('process', function ($q) use ($userId) {
            $q->where('responsible_user_id', $userId);
        });
    }

    public function progressPercentage(): int
    {
        $total = $this->items()->count();
        if ($total === 0) {
            return 0;
        }

        $answered = $this->items()->whereNotNull('response')->count();

        return (int) round(($answered / $total) * 100);
    }

    public function answeredCount(): int
    {
        return $this->items()->whereNotNull('response')->count();
    }

    public function totalCount(): int
    {
        return $this->items()->count();
    }
}
