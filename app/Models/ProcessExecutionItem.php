<?php

namespace App\Models;

use App\Enums\ProcessItemStatus;
use App\Enums\ProcessResponseType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessExecutionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'process_execution_id',
        'process_item_id',
        'question_snapshot',
        'response_type',
        'response',
        'notes',
        'answered_by',
        'answered_by_telegram_employee_id',
        'answered_at',
        'status',
    ];

    protected $casts = [
        'response_type' => ProcessResponseType::class,
        'status' => ProcessItemStatus::class,
        'answered_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProcessExecution::class, 'process_execution_id');
    }

    public function processItem(): BelongsTo
    {
        return $this->belongsTo(ProcessItem::class);
    }

    public function answeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
    }

    public function answeredByTelegramEmployee(): BelongsTo
    {
        return $this->belongsTo(TelegramEmployee::class, 'answered_by_telegram_employee_id');
    }

    public function answeredByPerson(): User|TelegramEmployee|null
    {
        return $this->answeredBy ?? $this->answeredByTelegramEmployee;
    }

    public function getAnsweredByNameAttribute(): ?string
    {
        return $this->answeredByPerson()?->name;
    }

    public function isAnswered(): bool
    {
        return $this->response !== null && $this->response !== '';
    }
}
