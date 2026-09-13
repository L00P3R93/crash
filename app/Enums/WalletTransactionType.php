<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    case BetDebit = 'bet_debit';
    case GameWin = 'game_win';
    case Topup = 'topup';
    case Withdrawal = 'withdrawal';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case WithholdingTax = 'withholding_tax';
}
