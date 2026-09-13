<?php

namespace App\Enums;

enum UssdSessionStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
