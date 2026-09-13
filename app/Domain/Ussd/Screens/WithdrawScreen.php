<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Aviator\Exceptions\WithdrawalsDisabledException;
use App\Domain\Mpesa\B2cService;
use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Models\UssdSession;
use Illuminate\Support\Str;

class WithdrawScreen extends AbstractUssdScreen
{
    public function __construct(
        private readonly B2cService $b2c = new B2cService
    ) {}

    public function handle(UssdSession $session, string $input): UssdResponse
    {
        $state = $session->state ?? [];
        $awaiting = $state['awaiting'] ?? null;

        if ($awaiting === 'withdraw_execute') {
            return $this->execute($session, (float) $state['amount']);
        }

        if ($awaiting === 'withdraw_confirm') {
            return $this->handleConfirm($session, $input, (float) $state['amount']);
        }

        if ($input === '') {
            return $this->stay($this->prompt($session));
        }

        if ($input === '0') {
            return $this->goTo($session, 'main_menu');
        }

        $min = (float) config('mpesa.min_withdrawal');
        $max = (float) config('mpesa.max_withdrawal');

        if (! is_numeric($input)) {
            return $this->invalidChoice($this->prompt($session));
        }

        $amount = (float) $input;
        $balance = (float) $session->player->wallet->fresh()->balance;

        if ($amount < $min || $amount > $max) {
            return $this->stay("Amount must be between KSh {$this->fmt($min)} and KSh {$this->fmt($max)}.\nEnter amount to withdraw (KSh {$this->fmt($min)}-{$this->fmt($max)})\n0: Menu");
        }

        if ($amount > $balance) {
            return $this->stay("Insufficient balance.\nBalance KSh {$this->fmt($balance)}\n0: Menu");
        }

        $session->state = ['awaiting' => 'withdraw_confirm', 'amount' => $amount];

        return $this->stay("Confirm withdraw KSh {$this->fmt($amount)} to {$session->msisdn}?\n1: Confirm\n2: Cancel");
    }

    private function handleConfirm(UssdSession $session, string $input, float $amount): UssdResponse
    {
        if ($input === '2') {
            $session->state = [];

            return $this->goTo($session, 'main_menu');
        }

        if ($input !== '1') {
            return $this->stay("Confirm withdraw KSh {$this->fmt($amount)} to {$session->msisdn}?\n1: Confirm\n2: Cancel");
        }

        // Gate the actual money movement behind PIN verification; withdraw()
        // itself runs once VerifyPinScreen routes back here.
        $session->state = ['awaiting' => 'withdraw_execute', 'amount' => $amount, 'verify_pin_next' => 'withdraw'];

        return $this->goTo($session, 'verify_pin');
    }

    private function execute(UssdSession $session, float $amount): UssdResponse
    {
        $session->state = [];

        $reference = 'AVIATOR-WD-'.Str::upper(Str::random(16));

        try {
            $this->b2c->initiate($session->player->wallet, $amount, $session->msisdn, $reference);
        } catch (InsufficientFundsException) {
            $balance = (float) $session->player->wallet->fresh()->balance;

            return $this->end("Insufficient balance.\nBalance KSh {$this->fmt($balance)}");
        } catch (WithdrawalsDisabledException $e) {
            return $this->end($e->playerMessage());
        }

        return $this->end("Withdrawal requested. KSh {$this->fmt($amount)} will be sent to your M-Pesa shortly.");
    }

    private function prompt(UssdSession $session): string
    {
        $balance = (float) $session->player->wallet->fresh()->balance;
        $min = (float) config('mpesa.min_withdrawal');
        $max = (float) config('mpesa.max_withdrawal');

        return "WITHDRAW\nBalance KSh {$this->fmt($balance)}\nEnter amount to withdraw (KSh {$this->fmt($min)}-{$this->fmt($max)})\n0: Menu";
    }
}
