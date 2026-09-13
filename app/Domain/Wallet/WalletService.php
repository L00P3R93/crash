<?php

namespace App\Domain\Wallet;

use App\Domain\Aviator\PlayerLimitsGuard;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class WalletService
{
    public function __construct(
        private readonly LedgerService $ledger = new LedgerService,
        private readonly PlayerLimitsGuard $limits = new PlayerLimitsGuard,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function debit(
        Wallet $wallet,
        float $amount,
        WalletTransactionType $type,
        string $reference,
        ?Model $related = null,
        ?array $metadata = null,
        WalletTransactionStatus $status = WalletTransactionStatus::Completed,
    ): WalletTransaction {
        $this->assertPositive($amount);

        return $this->ledger->write($wallet, $type, -$amount, $reference, $status, $related, $metadata);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function credit(
        Wallet $wallet,
        float $amount,
        WalletTransactionType $type,
        string $reference,
        ?Model $related = null,
        ?array $metadata = null,
        WalletTransactionStatus $status = WalletTransactionStatus::Completed,
    ): WalletTransaction {
        $this->assertPositive($amount);

        if ($type === WalletTransactionType::Topup) {
            $this->limits->assertCanDeposit($wallet->player, $amount);
        }

        return $this->ledger->write($wallet, $type, $amount, $reference, $status, $related, $metadata);
    }

    private function assertPositive(float $amount): void
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Wallet ledger amounts must be positive; debit/credit direction is chosen by the method.');
        }
    }
}
