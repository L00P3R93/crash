<?php

namespace Database\Factories;

use App\Models\LadderConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LadderConfig>
 */
class LadderConfigFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'default',
            'safe_ratio' => 1.10,
            'risky_ratio' => 2.00,
            'is_active' => true,
        ];
    }
}
