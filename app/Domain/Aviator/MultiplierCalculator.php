<?php

namespace App\Domain\Aviator;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

class MultiplierCalculator
{
    /**
     * Seconds from round start until the multiplier reaches $crashMultiplier,
     * given M(t) = e^(k*t). Computed once so the server never has to tick.
     */
    public function crashDelaySeconds(float $crashMultiplier, float $k): float
    {
        return log($crashMultiplier) / $k;
    }

    /**
     * The authoritative multiplier at $now, derived purely from elapsed time.
     * Capped at the round's crash multiplier — never reports past the crash.
     *
     * Accepts either Carbon flavor: `AviatorRound::started_at` comes back as
     * `CarbonImmutable` (AppServiceProvider sets that as the app-wide date
     * class), but callers may still hand in a plain `Carbon` instance.
     */
    public function currentMultiplier(CarbonInterface $startedAt, float $k, float $crashMultiplier, ?CarbonInterface $now = null): float
    {
        $now ??= Date::now();

        $elapsedSeconds = max(0, $startedAt->diffInMilliseconds($now, true) / 1000);

        $multiplier = exp($k * $elapsedSeconds);

        return min($multiplier, $crashMultiplier);
    }
}
