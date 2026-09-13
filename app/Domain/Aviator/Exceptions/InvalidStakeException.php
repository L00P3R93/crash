<?php

namespace App\Domain\Aviator\Exceptions;

use RuntimeException;

class InvalidStakeException extends RuntimeException implements HasPlayerMessage
{
    private string $playerMessage;

    public static function outOfBounds(float $stake, float $min, float $max): self
    {
        $e = new self("Stake {$stake} is outside the allowed range ({$min}-{$max}).");
        $e->playerMessage = "Stake must be between KSh {$min} and KSh {$max}.";

        return $e;
    }

    public function playerMessage(): string
    {
        return $this->playerMessage;
    }
}
