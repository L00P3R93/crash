<?php

namespace Tests\Unit\Filament;

use App\Filament\Widgets\GameLoopHealthWidget;
use PHPUnit\Framework\TestCase;

class GameLoopHealthWidgetTest extends TestCase
{
    public function test_threshold_comfortably_covers_a_full_round_at_max_multiplier(): void
    {
        $bettingWindowSeconds = 25;
        $maxMultiplier = 5000.0;
        $accelerationK = 0.25;
        $postRoundPauseSeconds = 15;

        $threshold = GameLoopHealthWidget::staleAfterSeconds(
            $bettingWindowSeconds,
            $maxMultiplier,
            $accelerationK,
            $postRoundPauseSeconds,
        );

        // A round riding all the way to max_multiplier takes ln(5000)/0.25
        // ~= 34.07s to crash — the threshold must clear the whole round
        // (betting + run + pause) with room to spare, not just the pieces
        // that were true under the old fixed 10s constant.
        $worstCaseRoundSeconds = $bettingWindowSeconds + (int) ceil(log($maxMultiplier) / $accelerationK) + $postRoundPauseSeconds;

        $this->assertGreaterThan($worstCaseRoundSeconds, $threshold);
    }

    public function test_threshold_still_flags_a_genuinely_dead_loop(): void
    {
        $threshold = GameLoopHealthWidget::staleAfterSeconds(
            bettingWindowSeconds: 25,
            maxMultiplier: 5000.0,
            accelerationK: 0.25,
            postRoundPauseSeconds: 15,
        );

        // Comfortably covering one slow round is the point of the fix —
        // it must not stretch so far that a worker dead for minutes still
        // reads as healthy.
        $this->assertLessThan(300, $threshold);
    }

    public function test_threshold_shrinks_and_grows_with_the_underlying_config(): void
    {
        $shortWindow = GameLoopHealthWidget::staleAfterSeconds(5, 5000.0, 0.25, 0);
        $longWindow = GameLoopHealthWidget::staleAfterSeconds(25, 5000.0, 0.25, 15);

        $this->assertGreaterThan($shortWindow, $longWindow);
    }
}
