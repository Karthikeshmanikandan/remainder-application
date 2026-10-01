<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'telegram_employee_id',
        'chat_id',
        'telegram_user_id',
        'telegram_username',
        'verification_code',
        'verification_expires_at',
        'verified_at',
        'is_active',
        'last_seen_at',
    ];

    protected $casts = [
        'verification_expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function telegramEmployee(): BelongsTo
    {
        return $this->belongsTo(TelegramEmployee::class);
    }

    public function owner(): User|TelegramEmployee|null
    {
        return $this->user ?? $this->telegramEmployee;
    }

    public function getOwnerNameAttribute(): string
    {
        return $this->owner()?->name ?? 'Unknown';
    }

    public function getUsernameAttribute(): ?string
    {
        return $this->telegram_username;
    }

    public function getTelegramChatIdAttribute(): ?string
    {
        return $this->chat_id;
    }

    public function getMaskedChatIdAttribute(): ?string
    {
        if (! $this->chat_id) {
            return null;
        }

        $length = strlen($this->chat_id);
        if ($length <= 4) {
            return str_repeat('•', $length);
        }

        return str_repeat('•', 6).substr($this->chat_id, -4);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null && $this->chat_id !== null;
    }

    public function isVerificationCodeExpired(): bool
    {
        return ! $this->verification_expires_at || $this->verification_expires_at->isPast();
    }

    public function hasValidVerificationCode(): bool
    {
        return ! empty($this->verification_code) && ! $this->isVerificationCodeExpired();
    }
}
