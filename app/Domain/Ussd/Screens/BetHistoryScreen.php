<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Enums\BetStatus;
use App\Models\AviatorBet;
use App\Models\UssdSession;

class BetHistoryScreen extends AbstractUssdScreen
{
    public function handle(UssdSession $session, string $input): UssdResponse
    {
        if ($input === '') {
            return $this->stay($this->render($session));
        }

        return $input === '0'
            ? $this->goTo($session, 'main_menu')
            : $this->invalidChoice($this->render($session));
    }

    private function render(UssdSession $session): string
    {
        $bets = $session->player->bets()
            ->whereIn('status', [BetStatus::Won, BetStatus::Lost])
            ->with('round')
            ->latest('placed_at')
            ->limit(5)
            ->get();

        if ($bets->isEmpty()) {
            return "RECENT ROUNDS\nNo rounds played yet.\n0: Menu";
        }

        $lines = $bets->map(function (AviatorBet $bet) {
            $result = $bet->status === BetStatus::Won ? 'WON' : 'LOST';
            $multiplier = $bet->status === BetStatus::Won ? $bet->cashout_multiplier : $bet->round->crash_multiplier;

            return "Round {$bet->round->round_number}: {$result} {$this->fmtMult((float) $multiplier)}x";
        })->implode("\n");

        return "RECENT ROUNDS\n{$lines}\n0: Menu";
    }
}
