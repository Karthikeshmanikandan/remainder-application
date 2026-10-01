<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\RecurringTaskStatus;
use App\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'code',
        'title',
        'description',
        'project_id',
        'assigned_to',
        'assigned_telegram_employee_id',
        'created_by',
        'priority',
        'frequency',
        'interval',
        'starts_at',
        'ends_at',
        'next_run_at',
        'last_run_at',
        'status',
        'auto_create_reminder',
        'reminder_offset_minutes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'frequency' => RecurrenceFrequency::class,
        'status' => RecurringTaskStatus::class,
        'priority' => TaskPriority::class,
        'auto_create_reminder' => 'boolean',
        'interval' => 'integer',
        'reminder_offset_minutes' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedTelegramEmployee(): BelongsTo
    {
        return $this->belongsTo(TelegramEmployee::class, 'assigned_telegram_employee_id');
    }

    public function responsiblePerson(): User|TelegramEmployee|null
    {
        return $this->assignee ?? $this->assignedTelegramEmployee;
    }

    public function getResponsibleNameAttribute(): string
    {
        return $this->responsiblePerson()?->name ?? 'Unassigned';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RecurringTaskOccurrence::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isActive(): bool
    {
        return $this->status === RecurringTaskStatus::ACTIVE;
    }

    public function isPaused(): bool
    {
        return $this->status === RecurringTaskStatus::PAUSED;
    }

    public function isCancelled(): bool
    {
        return $this->status === RecurringTaskStatus::CANCELLED;
    }

    public function isCompleted(): bool
    {
        return $this->status === RecurringTaskStatus::COMPLETED;
    }

    /**
     * Whether this definition has run past its end date.
     */
    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->next_run_at !== null
            && $this->next_run_at->gt($this->ends_at);
    }
}
