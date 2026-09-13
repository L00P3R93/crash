<?php

namespace App\Console\Commands\Aviator;

use App\Domain\Aviator\MultiplierCalculator;
use App\Domain\Aviator\RoundService;
use App\Enums\RoundStatus;
use App\Models\AviatorRound;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SettleExpiredRounds extends Command
{
    protected $signature = 'aviator:settle-expired-rounds';

    protected $description = 'Safety net: forces stuck rounds through crash/settle if the game loop worker died mid-round.';

    private const GRACE_SECONDS = 30;

    public function handle(RoundService $rounds, MultiplierCalculator $multiplier): int
    {
        $this->settleStuckRunningRounds($rounds, $multiplier);
        $this->settleStuckCrashedRounds($rounds);

        return self::SUCCESS;
    }

    private function settleStuckRunningRounds(RoundService $rounds, MultiplierCalculator $multiplier): void
    {
        $k = (float) config('aviator.acceleration_k');

        AviatorRound::query()
            ->where('status', RoundStatus::Running)
            ->whereNotNull('started_at')
            ->each(function (AviatorRound $round) use ($rounds, $multiplier, $k) {
                $crashDelay = $multiplier->crashDelaySeconds((float) $round->crash_multiplier, $k);
                $expectedCrashAt = $round->started_at->clone()->addSeconds((int) ceil($crashDelay));

                if (now()->lessThan($expectedCrashAt->addSeconds(self::GRACE_SECONDS))) {
                    return;
                }

                $this->safely(function () use ($rounds, $round) {
                    $rounds->crashRound($round);
                    $rounds->settleRound($round->fresh());
                }, $round);
            });
    }

    private function settleStuckCrashedRounds(RoundService $rounds): void
    {
        AviatorRound::query()
            ->where('status', RoundStatus::Crashed)
            ->whereNotNull('crashed_at')
            ->where('crashed_at', '<', now()->subSeconds(self::GRACE_SECONDS))
            ->each(fn (AviatorRound $round) => $this->safely(
                fn () => $rounds->settleRound($round), $round
            ));
    }

    private function safely(callable $callback, AviatorRound $round): void
    {
        try {
            $callback();

            $this->info("Force-settled stuck round {$round->round_number}.");
        } catch (Throwable $e) {
            Log::error('Failed to force-settle stuck Aviator round.', [
                'round_id' => $round->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
