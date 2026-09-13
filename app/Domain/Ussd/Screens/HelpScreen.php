<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Models\UssdSession;

class HelpScreen extends AbstractUssdScreen
{
    public function handle(UssdSession $session, string $input): UssdResponse
    {
        if ($input === '') {
            return $this->stay($this->render());
        }

        return match ($input) {
            '1' => $this->goTo($session, 'fairness'),
            '0' => $this->goTo($session, 'main_menu'),
            default => $this->invalidChoice($this->render()),
        };
    }

    /**
     * Includes the session-timeout policy (architecture doc §28.13 requires
     * it be documented somewhere visible to the player): an abandoned
     * session never leaves a bet dangling — it resolves naturally against
     * the round's real, already-determined outcome once the round settles,
     * exactly as if the player had simply never cashed out.
     */
    private function render(): string
    {
        return "HOW TO PLAY\nStake, then choose to Cash Out,\nContinue (safer) or Rocket\n(riskier) at each stage.\nCash out before it crashes to win.\nIf your session times out mid-round,\nit settles automatically against the\nround's real outcome - nothing is lost\nor forfeited by disconnecting.\n1: Verify Fairness\n0: Menu";
    }
}
