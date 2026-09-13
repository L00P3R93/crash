<?php

namespace Tests\Feature\Wallet;

use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\WalletService;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_credit_increases_balance_and_writes_a_ledger_entry(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        $transaction = app(WalletService::class)->credit(
            $wallet, 50, WalletTransactionType::Topup, 'TXN-CREDIT-1'
        );

        $this->assertSame('150.00', $transaction->balance_after);
        $this->assertSame('150.00', $wallet->fresh()->balance);
    }

    public function test_debit_decreases_balance(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 100]);

        app(WalletService::class)->debit(
            $wallet, 40, WalletTransactionType::BetDebit, 'TXN-DEBIT-1'
        );

        $this->assertSame('60.00', $wallet->fresh()->balance);
    }

    public function test_debit_beyond_balance_throws_and_leaves_balance_unchanged(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 10]);

        $this->expectException(InsufficientFundsException::class);

        try {
            app(WalletService::class)->debit(
                $wallet, 50, WalletTransactionType::BetDebit, 'TXN-DEBIT-FAIL'
            );
        } finally {
            $this->assertSame('10.00', $wallet->fresh()->balance);
        }
    }

    public function test_replaying_the_same_reference_does_not_apply_the_write_twice(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);
        $service = app(WalletService::class);

        $service->credit($wallet, 100, WalletTransactionType::Topup, 'TXN-IDEMPOTENT');
        $service->credit($wallet, 100, WalletTransactionType::Topup, 'TXN-IDEMPOTENT');

        $this->assertSame('100.00', $wallet->fresh()->balance);
        $this->assertSame(1, $wallet->transactions()->count());
    }
}
