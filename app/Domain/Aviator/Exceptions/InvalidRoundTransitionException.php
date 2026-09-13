<?php

namespace App\Domain\Aviator\Exceptions;

use App\Enums\RoundStatus;
use RuntimeException;

class InvalidRoundTransitionException extends RuntimeException
{
    public static function forRound(int $roundId, RoundStatus $actual, RoundStatus $expected): self
    {
        return new self(
            "Round {$roundId} is {$actual->value}, expected {$expected->value}."
        );
    }
}
