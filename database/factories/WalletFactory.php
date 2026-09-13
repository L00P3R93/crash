<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'player_id' => Player::factory(),
            'balance' => 0,
            'currency' => 'KES',
        ];
    }
}
