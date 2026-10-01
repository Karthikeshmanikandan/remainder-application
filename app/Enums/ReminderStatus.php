<?php

namespace App\Enums;

enum ReminderStatus: string
{
    case PENDING = 'pending';
    case TRIGGERED = 'triggered';
    case CANCELLED = 'cancelled';
}
