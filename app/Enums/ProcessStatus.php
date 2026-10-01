<?php

namespace App\Enums;

enum ProcessStatus: string
{
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
}
