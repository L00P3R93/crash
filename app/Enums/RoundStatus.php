<?php

namespace App\Enums;

enum RoundStatus: string
{
    case Scheduled = 'scheduled';
    case Betting = 'betting';
    case Running = 'running';
    case Crashed = 'crashed';
    case Settled = 'settled';
    case Cancelled = 'cancelled';
}
