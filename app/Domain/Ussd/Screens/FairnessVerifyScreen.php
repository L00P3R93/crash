<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Enums\RoundStatus;
use App\Models\AviatorRound;
use App\Models\UssdSession;

class FairnessVerifyScreen extends AbstractUssdScreen
{
    public function handle(UssdSession $session, string $input): UssdResponse
    {
        if ($input === '') {
            return $this->stay($this->render());
        }

        return $input === '0'
            ? $this->goTo($session, 'main_menu')
            : $this->invalidChoice($this->render());
    }

    private function render(): string
    {
        $round = AviatorRound::query()->latest('id')->first();

        if (! $round) {
            return "FAIRNESS\nNo round available yet.\n0: Menu";
        }

        $body = "FAIRNESS\nRound {$round->round_number}\nSeed hash: {$round->server_seed_hash}\n";

        $body .= $round->status === RoundStatus::Settled
            ? "Server seed: {$round->server_seed}\nClient seed: {$round->client_seed}\nNonce: {$round->nonce}\nCrash: {$this->fmtMult((float) $round->crash_multiplier)}x"
            : "Reveal seed after round ends\nto verify your result.";

        return $body."\n0: Menu";
    }
}
