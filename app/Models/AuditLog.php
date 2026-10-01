<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'actor_type',
        'actor_id',
        'action',
        'auditable_type',
        'auditable_id',
        'summary',
        'before_values',
        'after_values',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'action' => AuditAction::class,
        'before_values' => 'array',
        'after_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Audit logs are strictly append-only.
     * Updates and deletes are prohibited to maintain audit trail integrity.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Audit logs are append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new RuntimeException('Audit logs are append-only and cannot be deleted.');
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function getActorNameAttribute(): string
    {
        if ($this->actor) {
            return $this->actor->name;
        }

        if ($this->user) {
            return $this->user->name;
        }

        return 'System';
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForAuditable(Builder $query, Model $model): Builder
    {
        return $query->where('auditable_type', $model->getMorphClass())
            ->where('auditable_id', $model->getKey());
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAction(Builder $query, AuditAction|string $action): Builder
    {
        $val = $action instanceof AuditAction ? $action->value : $action;

        return $query->where('action', $val);
    }

    /**
     * Get a user-friendly name for the auditable entity.
     */
    public function getAuditableName(): string
    {
        $auditable = $this->auditable;
        if (! $auditable) {
            $type = class_basename($this->auditable_type);

            return "{$type} #{$this->auditable_id}";
        }

        if (isset($auditable->name)) {
            return (string) $auditable->name;
        }

        if (isset($auditable->question)) {
            return (string) $auditable->question;
        }

        if (isset($auditable->code)) {
            return (string) $auditable->code;
        }

        return class_basename($this->auditable_type)." #{$this->auditable_id}";
    }

    /**
     * Get entity class simple name.
     */
    public function getEntityTypeName(): string
    {
        return class_basename($this->auditable_type);
    }
}
