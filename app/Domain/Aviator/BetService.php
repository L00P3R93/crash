<?php

namespace App\Domain\Aviator;

use App\Domain\Aviator\Exceptions\InvalidStakeException;
use App\Domain\Aviator\Exceptions\RoundClosedException;
use App\Domain\Wallet\WalletService;
use App\Enums\BetStatus;
use App\Enums\RoundStatus;
use App\Enums\WalletTransactionType;
use App\Events\AviatorBetPlaced;
use App\Events\AviatorPublicBetPlaced;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Models\Player;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BetService
{
    public function __construct(
        private readonly WalletService $wallet = new WalletService,
        private readonly PlayerLimitsGuard $limits = new PlayerLimitsGuard,
    ) {}

    /**
     * Idempotent on `$betReference` (architecture doc §19): a retried request
     * with the same reference returns the bet already placed rather than
     * charging the player twice, whether the retry is detected up front or
     * races past that check and hits the unique constraint.
     */
    public function placeBet(
        Player $player,
        float $stake,
        string $channel,
        string $betReference,
        ?float $autoCashout = null,
    ): AviatorBet {
        $existing = AviatorBet::query()->where('bet_reference', $betReference)->first();

        if ($existing) {
            return $existing;
        }

        $minStake = (float) config('aviator.min_stake');
        $maxStake = (float) config('aviator.max_stake');

        if ($stake < $minStake || $stake > $maxStake) {
            throw InvalidStakeException::outOfBounds($stake, $minStake, $maxStake);
        }

        $this->limits->assertCanBet($player, $stake);

        $round = AviatorRound::query()
            ->where('status', RoundStatus::Betting)
            ->latest('id')
            ->first();

        if (! $round) {
            throw RoundClosedException::noOpenRound();
        }

        $wallet = $player->wallet ?? throw new ModelNotFoundException("Player {$player->id} has no wallet.");

        try {
            // Retries automatically on a MySQL deadlock (1213) — expected
            // under load here: dozens of bots and real players can all be
            // debiting different wallets inside overlapping transactions
            // the instant a round opens betting, and InnoDB occasionally
            // picks one as a deadlock victim purely from lock-ordering
            // timing, not any actual bug. MySQL's own error text says to
            // just retry; Laravel's $attempts param does exactly that.
            $bet = DB::transaction(function () use ($player, $wallet, $round, $stake, $channel, $betReference, $autoCashout) {
                $bet = AviatorBet::query()->create([
                    'round_id' => $round->id,
                    'player_id' => $player->id,
                    'wallet_id' => $wallet->id,
                    'bet_reference' => $betReference,
                    'channel' => $channel,
                    'stake' => $stake,
                    'auto_cashout' => $autoCashout,
                    'status' => BetStatus::Pending,
                    'placed_at' => now(),
                ]);

                $this->wallet->debit(
                    wallet: $wallet,
                    amount: $stake,
                    type: WalletTransactionType::BetDebit,
                    reference: $betReference,
                    related: $bet,
                );

                $bet->forceFill(['status' => BetStatus::Active])->save();

                return $bet;
            }, 3);

            event(new AviatorBetPlaced($bet));
            event(new AviatorPublicBetPlaced($bet));

            return $bet;
        } catch (QueryException $e) {
            if ($this->isDuplicateKeyError($e)) {
                return AviatorBet::query()->where('bet_reference', $betReference)->firstOrFail();
            }

            throw $e;
        }
    }

    /**
     * Resolves a bet the player never cashed out on as a loss. Used by the
     * USSD ladder when the chosen rung exceeds the round's already-known
     * crash point — there's no need to wait for the round's own settlement
     * sweep to reflect the outcome. Idempotent: a no-op if already resolved.
     */
    public function markLost(int $betId): AviatorBet
    {
        return DB::transaction(function () use ($betId) {
            $bet = AviatorBet::query()->lockForUpdate()->findOrFail($betId);

            if ($bet->status === BetStatus::Active) {
                $bet->forceFill(['status' => BetStatus::Lost, 'payout' => 0])->save();
            }

            return $bet;
        });
    }

    private function isDuplicateKeyError(QueryException $e): bool
    {
        return $e->getCode() === '23000';
    }
}
