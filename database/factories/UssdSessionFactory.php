<?php

namespace Database\Factories;

use App\Enums\UssdSessionStatus;
use App\Models\UssdSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UssdSession>
 */
class UssdSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => 'ATSession_'.Str::random(20),
            'msisdn' => '2547'.fake()->numerify('########'),
            'service_code' => '*384*1234#',
            'current_screen' => 'main_menu',
            'status' => UssdSessionStatus::Active,
            'started_at' => now(),
            'last_interaction_at' => now(),
        ];
    }
}
