<?php

namespace App\Domain\Aviator\Exceptions;

use RuntimeException;

class BetAlreadySettledException extends RuntimeException implements HasPlayerMessage
{
    private string $playerMessage;

    public static function forBet(int $betId): self
    {
        $e = new self("Bet {$betId} is no longer active.");
        $e->playerMessage = 'This bet has already been settled.';

        return $e;
    }

    public function playerMessage(): string
    {
        return $this->playerMessage;
    }
}
