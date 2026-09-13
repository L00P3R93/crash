<?php

namespace App\Domain\Aviator\Exceptions;

use RuntimeException;

class SelfExcludedException extends RuntimeException implements HasPlayerMessage
{
    private string $playerMessage;

    public static function forPlayer(int $playerId): self
    {
        $e = new self("Player {$playerId} is currently self-excluded.");
        $e->playerMessage = 'Your account is currently self-excluded from play.';

        return $e;
    }

    public function playerMessage(): string
    {
        return $this->playerMessage;
    }
}
