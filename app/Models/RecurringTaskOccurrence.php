<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecurringTaskOccurrence extends Model
{
    use HasFactory;

    protected $fillable = [
        'recurring_task_id',
        'occurrence_key',
        'scheduled_for',
        'task_id',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
    ];

    public function recurringTask(): BelongsTo
    {
        return $this->belongsTo(RecurringTask::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
