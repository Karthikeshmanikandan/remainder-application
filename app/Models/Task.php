<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
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
        'status',
        'due_date',
        'completed_at',
        'recurring_task_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedTelegramEmployee()
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

    public function getResponsibleTypeLabelAttribute(): string
    {
        if ($this->assigned_telegram_employee_id) {
            return 'Telegram Employee';
        }
        if ($this->assigned_to) {
            return 'Web User';
        }

        return 'Unassigned';
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reminders()
    {
        return $this->hasMany(TaskReminder::class);
    }

    public function recurringTask()
    {
        return $this->belongsTo(RecurringTask::class);
    }

    public function isOverdue(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        return $this->due_date->isPast()
            && $this->status !== TaskStatus::COMPLETED
            && $this->status !== TaskStatus::CANCELLED;
    }
}
