<?php

namespace App\Domain\Ussd;

final class UssdResponse
{
    public function __construct(
        public readonly string $body,
        public readonly bool $endSession = false,
    ) {}
}
