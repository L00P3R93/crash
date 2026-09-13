<?php

namespace App\Domain\Aviator\Exceptions;

use RuntimeException;

class DailyLimitExceededException extends RuntimeException implements HasPlayerMessage
{
    private string $playerMessage;

    public static function forStake(int $playerId): self
    {
        $e = new self("Player {$playerId} would exceed their daily stake limit with this bet.");
        $e->playerMessage = 'This bet would take you over your daily stake limit.';

        return $e;
    }

    public static function forDeposit(int $playerId): self
    {
        $e = new self("Player {$playerId} would exceed their daily deposit limit with this top-up.");
        $e->playerMessage = 'This top-up would take you over your daily deposit limit.';

        return $e;
    }

    public function playerMessage(): string
    {
        return $this->playerMessage;
    }
}
