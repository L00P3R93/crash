<?php

namespace App\Domain\Ussd\Contracts;

use App\Domain\Ussd\UssdResponse;
use App\Models\UssdSession;

interface UssdScreen
{
    /**
     * $input is '' the moment a screen is first shown (nothing to process
     * yet — just render the prompt); otherwise it's the digit(s) the player
     * just sent in answer to this screen's last prompt.
     */
    public function handle(UssdSession $session, string $input): UssdResponse;
}
