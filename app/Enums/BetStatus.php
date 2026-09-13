<?php

namespace App\Enums;

enum BetStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Won = 'won';
    case Lost = 'lost';
    case Cancelled = 'cancelled';
}
