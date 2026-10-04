<?php

namespace App\Enums;

enum EventStatus: string
{
    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
