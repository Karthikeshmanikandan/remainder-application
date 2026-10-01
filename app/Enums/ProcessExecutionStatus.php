<?php

namespace App\Enums;

enum ProcessExecutionStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case INCOMPLETE = 'incomplete';
    case MISSED = 'missed';
}
