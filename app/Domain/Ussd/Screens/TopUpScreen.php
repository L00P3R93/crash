<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Mpesa\StkPushService;
use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Enums\TopupProvider;
use App\Enums\TopupStatus;
use App\Models\Topup;
use App\Models\UssdSession;

class TopUpScreen extends AbstractUssdScreen
{
    public function __construct(
        private readonly StkPushService $stkPush = new StkPushService
    ) {}

    public function handle(UssdSession $session, string $input): UssdResponse
    {
        $state = $session->state ?? [];

        if (($state['awaiting'] ?? null) === 'topup_confirm') {
            return $this->handleConfirm($session, $input, (float) $state['amount']);
        }

        if ($input === '') {
            return $this->stay($this->prompt($session));
        }

        if ($input === '0') {
            return $this->goTo($session, 'main_menu');
        }

        $min = (float) config('mpesa.min_topup');
        $max = (float) config('mpesa.max_topup');

        if (! is_numeric($input)) {
            return $this->invalidChoice($this->prompt($session));
        }

        $amount = (float) $input;

        if ($amount < $min || $amount > $max) {
            return $this->stay("Amount must be between KSh {$this->fmt($min)} and KSh {$this->fmt($max)}.\nEnter amount to top up (KSh {$this->fmt($min)}-{$this->fmt($max)})\n0: Menu");
        }

        $session->state = ['awaiting' => 'topup_confirm', 'amount' => $amount];

        return $this->stay("Confirm top up KSh {$this->fmt($amount)} via M-PESA?\n1: Confirm\n2: Cancel");
    }

    private function handleConfirm(UssdSession $session, string $input, float $amount): UssdResponse
    {
        if ($input === '2') {
            $session->state = [];

            return $this->goTo($session, 'main_menu');
        }

        if ($input !== '1') {
            return $this->stay("Confirm top up KSh {$this->fmt($amount)} via M-PESA?\n1: Confirm\n2: Cancel");
        }

        $topup = Topup::query()->create([
            'player_id' => $session->player->id,
            'wallet_id' => $session->player->wallet->id,
            'amount' => $amount,
            'provider' => TopupProvider::Mpesa,
            'status' => TopupStatus::Pending,
            'requested_at' => now(),
        ]);

        $this->stkPush->initiate($topup, $session->msisdn);

        $session->state = [];

        // The balance hasn't actually moved yet — that only happens once
        // the async M-Pesa callback confirms payment (architecture doc §25:
        // never claim a state the server hasn't actually confirmed).
        return $this->end("STK push sent to your phone.\nEnter your M-Pesa PIN to complete.\nBalance updates once payment is confirmed.");
    }

    private function prompt(UssdSession $session): string
    {
        $balance = (float) $session->player->wallet->fresh()->balance;
        $min = (float) config('mpesa.min_topup');
        $max = (float) config('mpesa.max_topup');

        return "TOP UP\nBalance KSh {$this->fmt($balance)}\nEnter amount to top up (KSh {$this->fmt($min)}-{$this->fmt($max)})\n0: Menu";
    }
}
