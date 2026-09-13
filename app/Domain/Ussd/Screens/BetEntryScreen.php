<?php

namespace App\Domain\Ussd\Screens;

use App\Models\UssdSession;

class BetEntryScreen extends AbstractBetEntryScreen
{
    protected function initialPrompt(UssdSession $session): string
    {
        $balance = (float) $session->player->wallet->fresh()->balance;
        $rtp = round((1 - (float) config('aviator.house_edge')) * 100);
        $min = (float) config('aviator.min_stake');
        $max = (float) config('aviator.max_stake');
        $maxMultiplier = number_format((float) config('aviator.max_multiplier'), 0);

        return "AVIATOR\nBalance KSh {$this->fmt($balance)}\n{$rtp}% RTP | Up to {$maxMultiplier}x stake\nEnter bet KSh {$this->fmt($min)}-{$this->fmt($max)}\n0: Menu";
    }
}
