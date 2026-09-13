<?php

namespace Database\Factories;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10, 1000);

        return [
            'wallet_id' => Wallet::factory(),
            'reference' => 'TXN-'.Str::upper(Str::random(12)),
            'type' => WalletTransactionType::Topup,
            'amount' => $amount,
            'balance_before' => 0,
            'balance_after' => $amount,
            'status' => WalletTransactionStatus::Completed,
        ];
    }
}
