<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Enums\BetStatus;
use App\Models\UssdSession;

class AccountScreen extends AbstractUssdScreen
{
    public function handle(UssdSession $session, string $input): UssdResponse
    {
        if ($input === '') {
            return $this->stay($this->render($session));
        }

        return match ($input) {
            '1' => $this->goTo($session, 'bet_history'),
            '2' => $this->goTo($session, 'withdraw'),
            '0' => $this->goTo($session, 'main_menu'),
            default => $this->invalidChoice($this->render($session)),
        };
    }

    private function render(UssdSession $session): string
    {
        $player = $session->player;
        $balance = (float) $player->wallet->fresh()->balance;
        $staked = (float) $player->bets()->sum('stake');
        $won = (float) $player->bets()->where('status', BetStatus::Won)->sum('payout');

        return "ACCOUNT\nBalance KSh {$this->fmt($balance)}\nTotal staked: KSh {$this->fmt($staked)}\nTotal won: KSh {$this->fmt($won)}\n1: Bet History\n2: Withdraw\n0: Menu";
    }
}
