<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Models\UssdSession;
use Illuminate\Support\Facades\Hash;

class SetPinScreen extends AbstractUssdScreen
{
    public function handle(UssdSession $session, string $input): UssdResponse
    {
        $state = $session->state ?? [];

        if ($input === '') {
            return $this->stay($this->prompt());
        }

        if (! isset($state['pending_pin'])) {
            if (! $this->isValidPin($input)) {
                return $this->stay("PIN must be exactly 4 digits.\nEnter PIN:");
            }

            $session->state = ['pending_pin' => $input];

            return $this->stay('Confirm PIN:');
        }

        if ($input !== $state['pending_pin']) {
            $session->state = [];

            return $this->stay("PINs did not match. Try again.\nEnter PIN:");
        }

        $session->player->forceFill([
            'pin_hash' => Hash::make($input),
            'pin_set_at' => now(),
        ])->save();

        $session->state = [];

        return $this->goTo($session, 'main_menu');
    }

    private function prompt(): string
    {
        return "SET YOUR PIN\nFor your security, create a 4-digit PIN.\nEnter PIN:";
    }

    private function isValidPin(string $input): bool
    {
        return (bool) preg_match('/^\d{4}$/', $input);
    }
}
