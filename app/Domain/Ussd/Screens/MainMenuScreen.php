<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Enums\BetStatus;
use App\Models\AviatorBet;
use App\Models\UssdSession;
use App\Support\MsisdnMasker;

class MainMenuScreen extends AbstractUssdScreen
{
    public function handle(UssdSession $session, string $input): UssdResponse
    {
        if ($input === '') {
            return $this->stay($this->render());
        }

        return match ($input) {
            '1' => $this->goTo($session, 'bet_entry'),
            '2' => $this->goTo($session, 'topup'),
            '3' => $this->goTo($session, 'account'),
            '4' => $this->goTo($session, 'help'),
            default => $this->invalidChoice($this->render()),
        };
    }

    private function render(): string
    {
        $body = "Shinda Na Aviator\n1: Play Aviator\n2: Top Up\n3: Account\n4: Help";

        $pilots = $this->topPilots();

        if ($pilots !== '') {
            $body .= "\n\nTop Pilots:\n{$pilots}";
        }

        return $body;
    }

    private function topPilots(): string
    {
        return AviatorBet::query()
            ->where('status', BetStatus::Won)
            ->whereNotNull('cashout_multiplier')
            ->whereHas('player', fn ($query) => $query->where('is_bot', false))
            ->orderByDesc('cashout_multiplier')
            ->limit(2)
            ->with('player')
            ->get()
            ->map(fn (AviatorBet $bet) => MsisdnMasker::mask($bet->player->msisdn).' - '.$this->fmtMult((float) $bet->cashout_multiplier).'x')
            ->implode("\n");
    }
}
