<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Models\UssdSession;
use Illuminate\Support\Facades\Hash;

/**
 * A generic PIN gate in front of high-value actions (withdrawals). The
 * caller stores `verify_pin_next` in session state before transitioning
 * here; on success we jump straight there, carrying the rest of the state
 * forward untouched.
 */
class VerifyPinScreen extends AbstractUssdScreen
{
    private const MAX_ATTEMPTS = 3;

    public function handle(UssdSession $session, string $input): UssdResponse
    {
        if ($input === '') {
            return $this->stay('Enter your PIN:');
        }

        $state = $session->state ?? [];
        $player = $session->player;

        if ($player->pin_hash && Hash::check($input, $player->pin_hash)) {
            $next = $state['verify_pin_next'] ?? 'main_menu';
            $session->state = collect($state)->except(['pin_attempts', 'verify_pin_next'])->all();

            return $this->goTo($session, $next);
        }

        $attempts = (int) ($state['pin_attempts'] ?? 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $session->state = [];

            return $this->end("Too many incorrect PIN attempts.\nPlease try again later.");
        }

        $session->state = array_merge($state, ['pin_attempts' => $attempts]);

        return $this->stay('Incorrect PIN. Try again:');
    }
}
