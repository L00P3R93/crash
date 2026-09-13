<?php

namespace App\Domain\Aviator;

use App\Domain\Aviator\Exceptions\BetAlreadySettledException;
use App\Domain\Aviator\Exceptions\RoundClosedException;
use App\Domain\Wallet\WalletService;
use App\Enums\BetStatus;
use App\Enums\RoundStatus;
use App\Enums\WalletTransactionType;
use App\Events\AviatorBetCashedOut;
use App\Events\AviatorPublicBetCashedOut;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use Illuminate\Support\Facades\DB;

class CashoutService
{
    public function __construct(
        private readonly WalletService $wallet = new WalletService,
        private readonly MultiplierCalculator $multiplierCalculator = new MultiplierCalculator,
    ) {}

    /**
     * Authoritative cash-out (architecture doc §10/§17). Row-locks the bet and
     * round so a second concurrent cash-out attempt blocks until the first
     * commits, then sees `status !== active` and fails — no double payout.
     *
     * $atMultiplier lets an auto-cashout (or a USSD ladder step) specify the
     * exact multiplier it locked in at; when omitted, the server computes the
     * live multiplier from elapsed time — never trust a client-supplied one.
     */
    public function cashOut(int $betId, ?float $atMultiplier = null): AviatorBet
    {
        // Retries automatically on a MySQL deadlock (1213) — see the
        // matching comment in BetService::placeBet() for why this is
        // expected under load rather than a bug: this locks bet, round,
        // and wallet rows in sequence, and can occasionally lose a lock-
        // ordering race against another concurrent bet/cashout.
        $bet = DB::transaction(function () use ($betId, $atMultiplier) {
            $bet = AviatorBet::query()->lockForUpdate()->findOrFail($betId);

            if ($bet->status !== BetStatus::Active) {
                throw BetAlreadySettledException::forBet($bet->id);
            }

            $round = AviatorRound::query()->lockForUpdate()->findOrFail($bet->round_id);

            if ($round->status !== RoundStatus::Running && $round->status !== RoundStatus::Crashed) {
                throw RoundClosedException::alreadyCrashed($round->id);
            }

            $multiplier = $atMultiplier ?? $this->multiplierCalculator->currentMultiplier(
                $round->started_at,
                (float) config('aviator.acceleration_k'),
                (float) $round->crash_multiplier,
            );

            if ($multiplier >= (float) $round->crash_multiplier) {
                throw RoundClosedException::alreadyCrashed($round->id);
            }

            $grossPayout = round((float) $bet->stake * $multiplier, 2);
            $taxRate = (float) config('aviator.winnings_tax_rate');
            $taxWithheld = round($grossPayout * $taxRate, 2);
            $netPayout = round($grossPayout - $taxWithheld, 2);

            $bet->forceFill([
                'status' => BetStatus::Won,
                'cashout_multiplier' => $multiplier,
                'payout' => $netPayout,
                'cashed_out_at' => now(),
            ])->save();

            $this->wallet->credit(
                wallet: $bet->wallet,
                amount: $grossPayout,
                type: WalletTransactionType::GameWin,
                reference: "{$bet->bet_reference}-WIN",
                related: $bet,
                metadata: ['cashout_multiplier' => $multiplier, 'round_id' => $round->id],
            );

            if ($taxWithheld > 0) {
                $this->wallet->debit(
                    wallet: $bet->wallet,
                    amount: $taxWithheld,
                    type: WalletTransactionType::WithholdingTax,
                    reference: "{$bet->bet_reference}-TAX",
                    related: $bet,
                    metadata: ['tax_rate' => $taxRate, 'gross_payout' => $grossPayout],
                );
            }

            return $bet;
        }, 3);

        event(new AviatorBetCashedOut($bet));
        event(new AviatorPublicBetCashedOut($bet));

        return $bet;
    }
}
