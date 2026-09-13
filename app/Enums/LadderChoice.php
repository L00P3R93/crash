<?php

namespace App\Enums;

enum LadderChoice: string
{
    case CashOut = 'cash_out';
    case ContinueSafe = 'continue_safe';
    case RocketRisky = 'rocket_risky';
}
