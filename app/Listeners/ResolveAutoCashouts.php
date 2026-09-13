<?php

namespace App\Listeners;

use App\Domain\Aviator\CashoutService;
use App\Enums\BetStatus;
use App\Events\AviatorRoundCrashed;
use App\Models\AviatorBet;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Auto-cashout doesn't need to watch the multiplier tick — the crash point is
 * already known the instant the round crashes, so whether an auto-cashout
 * target was reached is a deterministic comparison (architecture doc §11).
 * Runs synchronously off AviatorRoundCrashed, before RoundScheduler calls
 * settleRound() — which would otherwise mark these same bets as lost.
 */
class ResolveAutoCashouts
{
    public function __construct(
        private readonly CashoutService $cashouts = new CashoutService
    ) {}

    public function handle(AviatorRoundCrashed $event): void
    {
        $round = $event->round;

        AviatorBet::query()
            ->where('round_id', $round->id)
            ->where('status', BetStatus::Active)
            ->whereNotNull('auto_cashout')
            ->where('auto_cashout', '<=', $round->crash_multiplier)
            ->get()
            ->each(function (AviatorBet $bet) {
                try {
                    $this->cashouts->cashOut($bet->id, (float) $bet->auto_cashout);
                } catch (Throwable $e) {
                    Log::error('Auto-cashout failed.', [
                        'bet_id' => $bet->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            });
    }
}
