<?php

namespace App\Models;

use App\Enums\ProcessEscalationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessEscalationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_execution_id',
        'escalation_rule_id',
        'level',
        'triggered_at',
        'acknowledged_at',
        'acknowledged_by',
        'status',
        'notification_status',
    ];

    protected $casts = [
        'level' => 'integer',
        'triggered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'status' => ProcessEscalationStatus::class,
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProcessExecution::class, 'process_execution_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(ProcessEscalationRule::class, 'escalation_rule_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function isTriggered(): bool
    {
        return $this->status === ProcessEscalationStatus::TRIGGERED;
    }

    public function isAcknowledged(): bool
    {
        return $this->status === ProcessEscalationStatus::ACKNOWLEDGED;
    }

    public function isResolved(): bool
    {
        return $this->status === ProcessEscalationStatus::RESOLVED;
    }

    public function isCancelled(): bool
    {
        return $this->status === ProcessEscalationStatus::CANCELLED;
    }
}
