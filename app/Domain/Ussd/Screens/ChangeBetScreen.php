<?php

namespace App\Domain\Ussd\Screens;

use App\Models\UssdSession;

class ChangeBetScreen extends AbstractBetEntryScreen
{
    protected function initialPrompt(UssdSession $session): string
    {
        $balance = (float) $session->player->wallet->fresh()->balance;

        return "AVIATOR\nBalance KSh {$this->fmt($balance)}\nEnter new bet KSh {$this->fmt((float) config('aviator.min_stake'))}-{$this->fmt((float) config('aviator.max_stake'))}\n0: Menu";
    }
}
