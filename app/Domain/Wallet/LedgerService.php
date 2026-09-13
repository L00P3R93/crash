<?php

namespace App\Domain\Wallet;

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place `wallets.balance` is ever written. Every write is a signed
 * amount (positive = credit, negative = debit) against a row-locked wallet,
 * paired with an append-only `wallet_transactions` entry — the ledger is the
 * source of truth, `balance` is just its running total (architecture doc §18).
 */
class LedgerService
{
    /**
     * Idempotent on `reference`: replaying the same reference returns the
     * transaction already written for it instead of applying it twice
     * (architecture doc §19).
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function write(
        Wallet $wallet,
        WalletTransactionType $type,
        float $amount,
        string $reference,
        WalletTransactionStatus $status = WalletTransactionStatus::Completed,
        ?Model $related = null,
        ?array $metadata = null,
    ): WalletTransaction {
        // Retries automatically on a MySQL deadlock (1213) — most relevant
        // to a caller like B2cService that invokes this as its own
        // top-level transaction rather than nested inside one of its own
        // (BetService/CashoutService already retry at their own outer
        // transaction boundary, since a deadlock kills the whole physical
        // transaction, not just this method's savepoint).
        return DB::transaction(function () use ($wallet, $type, $amount, $reference, $status, $related, $metadata) {
            $existing = WalletTransaction::query()->where('reference', $reference)->first();

            if ($existing) {
                return $existing;
            }

            $lockedWallet = Wallet::query()->lockForUpdate()->findOrFail($wallet->id);

            $balanceBefore = (float) $lockedWallet->balance;
            $balanceAfter = round($balanceBefore + $amount, 2);

            if ($balanceAfter < 0) {
                throw InsufficientFundsException::forWallet($lockedWallet->id);
            }

            $lockedWallet->forceFill(['balance' => $balanceAfter])->save();

            $transaction = new WalletTransaction([
                'wallet_id' => $lockedWallet->id,
                'reference' => $reference,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'status' => $status,
                'metadata' => $metadata,
            ]);

            if ($related) {
                $transaction->related()->associate($related);
            }

            $transaction->save();

            return $transaction;
        }, 3);
    }
}
