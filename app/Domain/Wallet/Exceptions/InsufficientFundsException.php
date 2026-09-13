<?php

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

class InsufficientFundsException extends RuntimeException
{
    public static function forWallet(int $walletId): self
    {
        return new self("Wallet {$walletId} has insufficient funds for this operation.");
    }
}
