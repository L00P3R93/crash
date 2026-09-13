<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Aviator\AviatorGameService;
use App\Domain\Aviator\Exceptions\RoundClosedException;
use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Models\UssdSession;
use Illuminate\Support\Str;

abstract class AbstractBetEntryScreen extends AbstractUssdScreen
{
    public function __construct(
        protected readonly AviatorGameService $game = new AviatorGameService
    ) {}

    abstract protected function initialPrompt(UssdSession $session): string;

    public function handle(UssdSession $session, string $input): UssdResponse
    {
        if ($input === '') {
            return $this->stay($this->initialPrompt($session));
        }

        if ($input === '0') {
            return $this->goTo($session, 'main_menu');
        }

        $min = (float) config('aviator.min_stake');
        $max = (float) config('aviator.max_stake');
        $rangeLine = "Enter bet KSh {$this->fmt($min)}-{$this->fmt($max)}\n0: Menu";

        if (! is_numeric($input)) {
            return $this->invalidChoice($this->initialPrompt($session));
        }

        $stake = (float) $input;

        if ($stake < $min) {
            return $this->stay("Minimum bet is KSh {$this->fmt($min)}.\n{$rangeLine}");
        }

        if ($stake > $max) {
            return $this->stay("Maximum bet is KSh {$this->fmt($max)}.\n{$rangeLine}");
        }

        $balance = (float) $session->player->wallet->fresh()->balance;

        if ($stake > $balance) {
            return $this->stay("Insufficient balance.\nBalance KSh {$this->fmt($balance)}\n2: Top Up\n0: Menu");
        }

        try {
            $bet = $this->game->placeBet(
                $session->player,
                $stake,
                'ussd',
                'AVIATOR-BET-'.Str::upper(Str::random(16)),
            );
        } catch (RoundClosedException) {
            return $this->stay("No round is open for betting right now.\nPlease wait for the next round.\n0: Menu");
        }

        $session->state = ['bet_id' => $bet->id, 'stake' => $stake];

        return $this->goTo($session, 'ladder_decision');
    }
}
