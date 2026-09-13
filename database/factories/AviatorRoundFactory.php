<?php

namespace Database\Factories;

use App\Enums\RoundStatus;
use App\Models\AviatorRound;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AviatorRound>
 */
class AviatorRoundFactory extends Factory
{
    public function definition(): array
    {
        $serverSeed = Str::random(32);

        return [
            'round_number' => fake()->unique()->numberBetween(1, 999999),
            'status' => RoundStatus::Scheduled,
            'server_seed_hash' => hash('sha256', $serverSeed),
            'client_seed' => Str::random(16),
            'nonce' => fake()->numberBetween(1, 100000),
            'house_edge' => 0.0300,
            'max_multiplier_cap' => 5000.00,
        ];
    }

    public function crashed(float $multiplier = 2.50): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RoundStatus::Crashed,
            'crash_multiplier' => $multiplier,
            'started_at' => now()->subSeconds(10),
            'crashed_at' => now(),
        ]);
    }
}
