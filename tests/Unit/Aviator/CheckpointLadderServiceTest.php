<?php

namespace Tests\Unit\Aviator;

use App\Domain\Aviator\CheckpointLadderService;
use PHPUnit\Framework\TestCase;

class CheckpointLadderServiceTest extends TestCase
{
    /**
     * Matches the observed USSD screens in architecture doc §27.1/§27.3:
     * from the 1.00x baseline, "Continue to 1.10x | 88 pct" and
     * "Rocket to 2.00x | 48 pct".
     */
    public function test_first_rung_probabilities_use_the_raw_survival_formula(): void
    {
        $service = new CheckpointLadderService;

        $rungs = $service->nextRungs(current: 1.00, houseEdge: 0.03, safeRatio: 1.10, riskyRatio: 2.00);

        $this->assertEqualsWithDelta(1.10, $rungs['safe']['target'], 0.001);
        $this->assertEqualsWithDelta(0.88, $rungs['safe']['probability'], 0.005);

        $this->assertEqualsWithDelta(2.00, $rungs['risky']['target'], 0.001);
        $this->assertEqualsWithDelta(0.485, $rungs['risky']['probability'], 0.005);
    }

    /**
     * From 3.00x already reached: "Continue to 4.00x | 75 pct",
     * "Rocket to 7.50x | 40 pct" (architecture doc §27.3).
     */
    public function test_later_rungs_use_the_conditional_probability_formula(): void
    {
        $service = new CheckpointLadderService;

        $this->assertEqualsWithDelta(0.75, $service->conditionalProbability(3.00, 4.00, 0.03), 0.0001);
        $this->assertEqualsWithDelta(0.40, $service->conditionalProbability(3.00, 7.50, 0.03), 0.0001);
    }

    public function test_resolve_step_checks_against_the_stored_crash_multiplier(): void
    {
        $service = new CheckpointLadderService;

        $this->assertTrue($service->resolveStep(crashMultiplier: 13.04, chosenRung: 3.00));
        $this->assertFalse($service->resolveStep(crashMultiplier: 1.58, chosenRung: 3.00));
    }
}
