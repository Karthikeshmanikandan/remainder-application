<?php

namespace App\Enums;

enum ProcessResponseType: string
{
    case YES_NO = 'yes_no';
    case YES_NO_NA = 'yes_no_na';
    case TEXT = 'text';
    case NUMBER = 'number';
}
