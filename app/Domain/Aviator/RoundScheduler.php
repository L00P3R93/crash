<?php

namespace App\Domain\Aviator;

use App\Models\AviatorRound;

class RoundScheduler
{
    public function __construct(
        private readonly RoundService $rounds = new RoundService,
        private readonly MultiplierCalculator $multiplier = new MultiplierCalculator
    ) {}

    /**
     * Drives one round through its full lifecycle. Sleeps for the betting
     * window and the pre-computed crash delay instead of ticking — the crash
     * time is derived once from the stored crash multiplier (architecture
     * doc §22), never recalculated on a timer.
     */
    public function runOnce(): AviatorRound
    {
        $round = $this->rounds->createRound();
        $this->rounds->openBetting($round);

        $this->sleepUntil($round->betting_closes_at->getPreciseTimestamp(6) / 1_000_000);

        $round = $this->rounds->startRound($round->fresh());

        $k = (float) config('aviator.acceleration_k');
        $crashDelay = $this->multiplier->crashDelaySeconds((float) $round->crash_multiplier, $k);

        // Sub-second precision matters here: rounding this up to the next
        // whole second (the old behavior) let the server keep a round
        // "running" for up to ~1s after the client's own local animation
        // — which runs the identical M(t) = e^(k*t) formula from
        // `started_at`, see resources/js/play.js's startMultiplierLoop() —
        // had already reached the true crash multiplier. The display would
        // keep climbing past the number the crash event eventually revealed,
        // then visibly snap back down. Sleeping to the exact fractional
        // instant instead means the only remaining gap is real broadcast
        // delivery latency (tens of ms), not an artifact of this scheduler.
        $this->sleepUntil($round->started_at->getPreciseTimestamp(6) / 1_000_000 + $crashDelay);

        $round = $this->rounds->crashRound($round->fresh());
        $round = $this->rounds->settleRound($round);

        // Held here, after settling, rather than at the top of the next
        // runOnce() call — the crash reveal/result banner only resets on the
        // next round's `betting_opened` broadcast, so this is what actually
        // keeps that display up long enough to read before betting reopens.
        sleep((int) config('aviator.post_round_pause_seconds'));

        return $round;
    }

    /**
     * Sleeps to a precise fractional-second unix timestamp. Split into a
     * whole-second `sleep()` plus a `usleep()` for the remainder rather than
     * a single `usleep()` call for the whole duration — `usleep()` is fine
     * for sub-second waits but isn't the right primitive for the multi-second
     * ones this also has to handle (the betting window is tens of seconds).
     */
    private function sleepUntil(float $unixTimestamp): void
    {
        $remaining = $unixTimestamp - microtime(true);

        if ($remaining <= 0) {
            return;
        }

        $wholeSeconds = (int) floor($remaining);

        if ($wholeSeconds > 0) {
            sleep($wholeSeconds);
        }

        $remainingMicroseconds = (int) round(($remaining - $wholeSeconds) * 1_000_000);

        if ($remainingMicroseconds > 0) {
            usleep($remainingMicroseconds);
        }
    }
}
