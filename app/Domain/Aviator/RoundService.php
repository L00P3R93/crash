<?php

namespace App\Domain\Aviator;

use App\Domain\Aviator\Exceptions\InvalidRoundTransitionException;
use App\Enums\BetStatus;
use App\Enums\RoundStatus;
use App\Events\AviatorBettingOpened;
use App\Events\AviatorRoundCrashed;
use App\Events\AviatorRoundResults;
use App\Events\AviatorRoundSettled;
use App\Events\AviatorRoundStarted;
use App\Models\AviatorRound;
use Illuminate\Support\Facades\DB;

class RoundService
{
    public function __construct(
        private readonly ProvablyFairService $provablyFair = new ProvablyFairService
    ) {}

    /**
     * Generates and stores everything about the round up front — including the
     * crash point — but keeps the server seed hidden (architecture doc §5/§6).
     * The round starts life as `scheduled`.
     */
    public function createRound(): AviatorRound
    {
        $houseEdge = (float) config('aviator.house_edge');
        $maxMultiplierCap = (float) config('aviator.max_multiplier');

        $serverSeed = $this->provablyFair->generateServerSeed();
        $clientSeed = $this->provablyFair->generateClientSeed();
        $roundNumber = $this->nextRoundNumber();
        $nonce = $roundNumber;

        $crashMultiplier = min(
            $this->provablyFair->crashPointFor($serverSeed, $clientSeed, $nonce, $houseEdge),
            $maxMultiplierCap
        );

        return AviatorRound::query()->create([
            'round_number' => $roundNumber,
            'status' => RoundStatus::Scheduled,
            'server_seed' => $serverSeed,
            'server_seed_hash' => $this->provablyFair->hashServerSeed($serverSeed),
            'client_seed' => $clientSeed,
            'nonce' => $nonce,
            'house_edge' => $houseEdge,
            'crash_multiplier' => $crashMultiplier,
            'max_multiplier_cap' => $maxMultiplierCap,
        ]);
    }

    public function openBetting(AviatorRound $round): AviatorRound
    {
        $this->assertStatus($round, RoundStatus::Scheduled);

        $bettingWindow = (int) config('aviator.betting_window_seconds');

        $round->forceFill([
            'status' => RoundStatus::Betting,
            'betting_closes_at' => now()->addSeconds($bettingWindow),
        ])->save();

        event(new AviatorBettingOpened($round));

        return $round;
    }

    public function startRound(AviatorRound $round): AviatorRound
    {
        $this->assertStatus($round, RoundStatus::Betting);

        $round->forceFill([
            'status' => RoundStatus::Running,
            'started_at' => now(),
        ])->save();

        event(new AviatorRoundStarted($round));

        return $round;
    }

    public function crashRound(AviatorRound $round): AviatorRound
    {
        $this->assertStatus($round, RoundStatus::Running);

        $round->forceFill([
            'status' => RoundStatus::Crashed,
            'crashed_at' => now(),
        ])->save();

        event(new AviatorRoundCrashed($round));

        return $round;
    }

    /**
     * Reveals the server seed and closes the round out. Any bet still `active`
     * at this point never cashed out in time, so it's a loss — no wallet
     * interaction here, that's Phase 2's CashoutService's job before this runs.
     */
    public function settleRound(AviatorRound $round): AviatorRound
    {
        $this->assertStatus($round, RoundStatus::Crashed);

        $round = DB::transaction(function () use ($round) {
            $round->bets()
                ->where('status', BetStatus::Active)
                ->update([
                    'status' => BetStatus::Lost,
                    'payout' => 0,
                ]);

            $round->forceFill([
                'status' => RoundStatus::Settled,
                'settled_at' => now(),
            ])->save();

            return $round;
        });

        event(new AviatorRoundSettled($round));
        event(new AviatorRoundResults($round));

        return $round;
    }

    private function nextRoundNumber(): int
    {
        return (int) DB::transaction(
            fn () => (AviatorRound::query()->lockForUpdate()->max('round_number') ?? 0) + 1
        );
    }

    private function assertStatus(AviatorRound $round, RoundStatus $expected): void
    {
        if ($round->status !== $expected) {
            throw InvalidRoundTransitionException::forRound($round->id, $round->status, $expected);
        }
    }
}
