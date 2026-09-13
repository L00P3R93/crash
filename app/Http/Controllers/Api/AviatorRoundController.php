<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AviatorRoundResource;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Support\MsisdnMasker;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated — anyone can watch the round the same way a
 * bystander could watch a USSD player's screen. Placing a bet is the only
 * part that needs a logged-in player (AviatorBetController).
 */
class AviatorRoundController extends Controller
{
    public function current(): JsonResponse
    {
        $round = AviatorRound::query()->latest('id')->first();

        if (! $round) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => new AviatorRoundResource($round)]);
    }

    /**
     * Every bet placed on the current round, masked the same way the public
     * feed.bet_placed/feed.bet_cashed_out/round.results broadcasts are.
     * Exists purely to hydrate the live "active players" table for a client
     * that loads (or reconnects) mid-round, after bets it never saw
     * broadcast were already placed.
     */
    public function players(): JsonResponse
    {
        $round = AviatorRound::query()->latest('id')->first();

        if (! $round) {
            return response()->json(['data' => ['round_number' => null, 'players' => []]]);
        }

        $players = AviatorBet::query()
            ->where('round_id', $round->id)
            ->with('player')
            ->orderBy('placed_at')
            ->get()
            ->map(fn (AviatorBet $bet) => [
                'bet_id' => $bet->id,
                'msisdn' => MsisdnMasker::mask($bet->player->msisdn),
                'stake' => (float) $bet->stake,
                'status' => $bet->status->value,
                'cashout_multiplier' => $bet->cashout_multiplier !== null ? (float) $bet->cashout_multiplier : null,
                'payout' => $bet->payout !== null ? (float) $bet->payout : null,
            ])
            ->values();

        return response()->json(['data' => [
            'round_number' => $round->round_number,
            'players' => $players,
        ]]);
    }
}
