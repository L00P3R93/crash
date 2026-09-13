<?php

namespace Database\Factories;

use App\Enums\PlayerStatus;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'msisdn' => '2547'.fake()->unique()->numerify('########'),
            'name' => fake()->optional()->name(),
            'status' => PlayerStatus::Active,
            'registered_via' => 'ussd',
            'last_seen_at' => now(),
        ];
    }

    public function withPin(string $pin = '1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'pin_hash' => bcrypt($pin),
            'pin_set_at' => now(),
        ]);
    }
}
