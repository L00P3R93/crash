<?php

namespace App\Domain\Ussd;

use App\Domain\Ussd\Contracts\UssdScreen;
use App\Models\UssdSession;

abstract class AbstractUssdScreen implements UssdScreen
{
    protected function goTo(UssdSession $session, string $screenKey, string $input = ''): UssdResponse
    {
        return app(UssdMenuRenderer::class)->render($session, $screenKey, $input);
    }

    protected function stay(string $body): UssdResponse
    {
        return new UssdResponse($body, endSession: false);
    }

    protected function end(string $body): UssdResponse
    {
        return new UssdResponse($body, endSession: true);
    }

    protected function fmt(float $value): string
    {
        return number_format($value, 2);
    }

    protected function fmtMult(float $value): string
    {
        return number_format($value, 2);
    }

    protected function invalidChoice(string $body): UssdResponse
    {
        return $this->stay("Invalid choice. Please try again.\n\n{$body}");
    }
}
