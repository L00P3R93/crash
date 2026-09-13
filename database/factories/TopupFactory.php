<?php

namespace Database\Factories;

use App\Enums\TopupProvider;
use App\Enums\TopupStatus;
use App\Models\Player;
use App\Models\Topup;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Topup>
 */
class TopupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'wallet_id' => Wallet::factory(),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'provider' => TopupProvider::Mpesa,
            'status' => TopupStatus::Pending,
            'requested_at' => now(),
        ];
    }
}
