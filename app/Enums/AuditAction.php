<?php

namespace App\Enums;

enum AuditAction: string
{
    case CREATED = 'CREATED';
    case UPDATED = 'UPDATED';
    case DELETED = 'DELETED';
    case ACTIVATED = 'ACTIVATED';
    case DEACTIVATED = 'DEACTIVATED';
    case PAUSED = 'PAUSED';
    case RESUMED = 'RESUMED';
    case CANCELLED = 'CANCELLED';
    case ASSIGNED = 'ASSIGNED';
    case REASSIGNED = 'REASSIGNED';
    case COMPLETED = 'COMPLETED';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case CONFIGURED = 'CONFIGURED';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Created',
            self::UPDATED => 'Updated',
            self::DELETED => 'Deleted',
            self::ACTIVATED => 'Activated',
            self::DEACTIVATED => 'Deactivated',
            self::PAUSED => 'Paused',
            self::RESUMED => 'Resumed',
            self::CANCELLED => 'Cancelled',
            self::ASSIGNED => 'Assigned',
            self::REASSIGNED => 'Reassigned',
            self::COMPLETED => 'Completed',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::CONFIGURED => 'Configured',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::CREATED => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            self::UPDATED => 'bg-blue-50 text-blue-700 border-blue-200',
            self::DELETED => 'bg-red-50 text-red-700 border-red-200',
            self::ACTIVATED, self::RESUMED => 'bg-teal-50 text-teal-700 border-teal-200',
            self::DEACTIVATED, self::PAUSED => 'bg-amber-50 text-amber-700 border-amber-200',
            self::CANCELLED => 'bg-rose-50 text-rose-700 border-rose-200',
            self::ASSIGNED, self::REASSIGNED => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            self::COMPLETED => 'bg-green-50 text-green-700 border-green-200',
            self::ACKNOWLEDGED => 'bg-cyan-50 text-cyan-700 border-cyan-200',
            self::CONFIGURED => 'bg-purple-50 text-purple-700 border-purple-200',
        };
    }
}
