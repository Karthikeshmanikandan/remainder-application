<?php

namespace App\Models;

use App\Enums\ReminderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'user_id',
        'remind_at',
        'status',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'triggered_at' => 'datetime',
        'read_at' => 'datetime',
        'status' => ReminderStatus::class,
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === ReminderStatus::PENDING;
    }

    public function isTriggered(): bool
    {
        return $this->status === ReminderStatus::TRIGGERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ReminderStatus::CANCELLED;
    }
}
