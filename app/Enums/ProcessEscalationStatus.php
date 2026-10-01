<?php

namespace App\Enums;

enum ProcessEscalationStatus: string
{
    case PENDING = 'pending';
    case TRIGGERED = 'triggered';
    case ACKNOWLEDGED = 'acknowledged';
    case RESOLVED = 'resolved';
    case CANCELLED = 'cancelled';
}
