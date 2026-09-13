<?php

namespace Tests\Unit\Aviator;

use App\Domain\Aviator\CrashPointGenerator;
use PHPUnit\Framework\TestCase;

class CrashPointGeneratorTest extends TestCase
{
    public function test_same_inputs_always_reproduce_the_same_crash_point(): void
    {
        $generator = new CrashPointGenerator;

        $first = $generator->generate('server-seed-abc', 'client-seed-123', 18291, 0.03);
        $second = $generator->generate('server-seed-abc', 'client-seed-123', 18291, 0.03);

        $this->assertSame($first, $second);
    }

    public function test_changing_the_nonce_changes_the_crash_point(): void
    {
        $generator = new CrashPointGenerator;

        $a = $generator->generate('server-seed-abc', 'client-seed-123', 1, 0.03);
        $b = $generator->generate('server-seed-abc', 'client-seed-123', 2, 0.03);

        $this->assertNotSame($a, $b);
    }

    public function test_changing_the_server_seed_changes_the_crash_point(): void
    {
        $generator = new CrashPointGenerator;

        $a = $generator->generate('server-seed-one', 'client-seed-123', 18291, 0.03);
        $b = $generator->generate('server-seed-two', 'client-seed-123', 18291, 0.03);

        $this->assertNotSame($a, $b);
    }

    public function test_crash_point_is_never_below_one(): void
    {
        $generator = new CrashPointGenerator;

        for ($nonce = 1; $nonce <= 2000; $nonce++) {
            $multiplier = $generator->generate('server-seed-floor-check', 'client-seed', $nonce, 0.03);

            $this->assertGreaterThanOrEqual(1.00, $multiplier);
        }
    }

    public function test_crash_point_is_rounded_down_to_two_decimals(): void
    {
        $generator = new CrashPointGenerator;

        for ($nonce = 1; $nonce <= 500; $nonce++) {
            $multiplier = $generator->generate('server-seed-rounding-check', 'client-seed', $nonce, 0.03);

            $this->assertSame(
                round($multiplier, 2),
                $multiplier,
                "Multiplier {$multiplier} at nonce {$nonce} has more than 2 decimal places."
            );
        }
    }

    /**
     * P(Crash >= x) ~= (1 - houseEdge) / x, per architecture doc §2.
     * Sample many independent draws and check the survival rate at a
     * threshold lands within a statistical tolerance of the formula.
     */
    public function test_distribution_matches_the_provably_fair_formula(): void
    {
        $generator = new CrashPointGenerator;
        $houseEdge = 0.03;
        $threshold = 2.00;
        $samples = 20000;

        $reachedThreshold = 0;

        for ($nonce = 1; $nonce <= $samples; $nonce++) {
            $multiplier = $generator->generate('server-seed-distribution', "client-{$nonce}", $nonce, $houseEdge);

            if ($multiplier >= $threshold) {
                $reachedThreshold++;
            }
        }

        $observedProbability = $reachedThreshold / $samples;
        $expectedProbability = (1 - $houseEdge) / $threshold;

        $this->assertEqualsWithDelta($expectedProbability, $observedProbability, 0.02);
    }
}
