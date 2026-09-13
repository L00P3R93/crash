<?php

namespace Database\Factories;

use App\Enums\BetStatus;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AviatorBet>
 */
class AviatorBetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'round_id' => AviatorRound::factory(),
            'player_id' => Player::factory(),
            'wallet_id' => Wallet::factory(),
            'bet_reference' => 'AVIATOR-BET-'.Str::upper(Str::random(16)),
            'channel' => 'ussd',
            'stake' => fake()->randomFloat(2, 10, 500),
            'current_rung' => 1.00,
            'status' => BetStatus::Pending,
            'placed_at' => now(),
        ];
    }
}
