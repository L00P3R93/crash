<?php

namespace App\Domain\Aviator\Exceptions;

use RuntimeException;

class WithdrawalsDisabledException extends RuntimeException implements HasPlayerMessage
{
    private string $playerMessage;

    public static function forPlayer(int $playerId): self
    {
        $e = new self("Player {$playerId} has withdrawals disabled.");
        $e->playerMessage = 'Withdrawals are not available for this account.';

        return $e;
    }

    public function playerMessage(): string
    {
        return $this->playerMessage;
    }
}
