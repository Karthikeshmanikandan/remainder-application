<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcessEscalationRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_id',
        'level',
        'delay_minutes',
        'escalate_to_user_id',
        'is_active',
    ];

    protected $casts = [
        'level' => 'integer',
        'delay_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function escalateToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalate_to_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProcessEscalationEvent::class, 'escalation_rule_id');
    }
}
