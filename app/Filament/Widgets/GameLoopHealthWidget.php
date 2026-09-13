<?php

namespace App\Filament\Widgets;

use App\Domain\Aviator\MultiplierCalculator;
use App\Enums\BetStatus;
use App\Models\AviatorBet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Surfaces the `aviator:run-game-loop` worker heartbeat written by
 * RunGameLoop. A dead loop means rounds silently stop — this is the first
 * place that would show it.
 */
class GameLoopHealthWidget extends BaseWidget
{
    /**
     * On top of the theoretical worst-case round duration below, in case of
     * GC pauses, broadcast jitter, or a retried iteration after a transient
     * failure (RunGameLoop still refreshes the heartbeat every attempt, but
     * one running long shouldn't itself read as "the loop died").
     */
    private const SAFETY_MARGIN_SECONDS = 15;

    private const DRIFT_THRESHOLD = 0.05;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 3;

    protected ?string $pollingInterval = '5s';

    protected ?string $heading = 'Game Health';

    protected ?string $description = 'Is the round engine running, and is the payout math behaving as configured?';

    /**
     * The heartbeat is written once, at the top of each `runOnce()` call —
     * a fixed threshold drifts stale (in the "constantly cries wolf" sense)
     * the moment any of the round-timing config changes, so this derives it
     * from the actual worst case instead: the full betting window, plus the
     * longest possible run phase (a round capped at `max_multiplier`), plus
     * the post-round pause.
     */
    public static function staleAfterSeconds(
        int $bettingWindowSeconds,
        float $maxMultiplier,
        float $accelerationK,
        int $postRoundPauseSeconds,
    ): int {
        $maxCrashDelay = (new MultiplierCalculator)->crashDelaySeconds($maxMultiplier, $accelerationK);

        $maxRoundSeconds = $bettingWindowSeconds + (int) ceil($maxCrashDelay) + $postRoundPauseSeconds;

        return $maxRoundSeconds + self::SAFETY_MARGIN_SECONDS;
    }

    protected function getStats(): array
    {
        $heartbeat = Cache::get('aviator:game_loop:heartbeat');

        if (! $heartbeat) {
            return [
                Stat::make('Game loop', 'No heartbeat')
                    ->description('aviator:run-game-loop does not appear to be running')
                    ->descriptionIcon('heroicon-o-exclamation-triangle')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('danger'),
            ];
        }

        $ageSeconds = Carbon::now()->diffInSeconds(Carbon::parse($heartbeat));

        $staleAfterSeconds = self::staleAfterSeconds(
            (int) config('aviator.betting_window_seconds'),
            (float) config('aviator.max_multiplier'),
            (float) config('aviator.acceleration_k'),
            (int) config('aviator.post_round_pause_seconds'),
        );

        $healthy = $ageSeconds <= $staleAfterSeconds;

        $staked = (float) AviatorBet::query()->whereIn('status', [BetStatus::Won, BetStatus::Lost])->sum('stake');
        $paid = (float) AviatorBet::query()->where('status', BetStatus::Won)->sum('payout');
        $configuredRtp = 1 - (float) config('aviator.house_edge');

        $actualRtp = $staked > 0 ? $paid / $staked : null;
        $drift = $actualRtp === null ? null : abs($actualRtp - $configuredRtp);
        $isDrifting = $drift !== null && $drift > self::DRIFT_THRESHOLD;

        return [
            Stat::make('Game loop', $healthy ? 'Healthy' : 'Stale')
                ->description($healthy ? "Last heartbeat {$ageSeconds}s ago" : "No heartbeat for {$ageSeconds}s — rounds may have stopped")
                ->descriptionIcon($healthy ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
                ->icon('heroicon-o-cog-6-tooth')
                ->color($healthy ? 'success' : 'danger'),

            Stat::make('Configured RTP', number_format($configuredRtp * 100, 1).'%')
                ->description('Set via Game Settings')
                ->icon('heroicon-o-adjustments-horizontal')
                ->color('gray'),
            Stat::make('Actual RTP', $actualRtp === null ? 'No settled bets yet' : number_format($actualRtp * 100, 1).'%')
                ->description($isDrifting ? 'Drifting from configured RTP — investigate the RNG/ladder math' : 'Within expected range')
                ->descriptionIcon($isDrifting ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                ->icon('heroicon-o-scale')
                ->color($isDrifting ? 'danger' : 'success'),
        ];
    }
}
