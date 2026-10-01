<?php

namespace App\Enums;

enum ProcessItemStatus: string
{
    case PENDING = 'pending';
    case ANSWERED = 'answered';
    case SKIPPED = 'skipped';
}
