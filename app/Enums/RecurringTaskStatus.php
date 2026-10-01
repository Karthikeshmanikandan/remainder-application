<?php

namespace App\Enums;

enum RecurringTaskStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
