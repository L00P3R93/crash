<?php

namespace Database\Factories;

use App\Enums\LadderChoice;
use App\Models\AviatorBet;
use App\Models\AviatorBetLadderStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AviatorBetLadderStep>
 */
class AviatorBetLadderStepFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bet_id' => AviatorBet::factory(),
            'step_number' => 1,
            'from_multiplier' => 1.00,
            'to_multiplier' => 1.10,
            'choice' => LadderChoice::ContinueSafe,
            'probability_shown' => 0.88,
        ];
    }
}
