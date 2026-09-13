<?php

namespace App\Events;

use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Support\MsisdnMasker;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The authoritative, final outcome of every bet in the round — fired after
 * settleRound() has already bulk-marked any still-active bets lost, so it's
 * the one payload a client can use to reconcile the whole live-players table
 * in one shot regardless of which feed.bet_placed/feed.bet_cashed_out
 * broadcasts it may have missed.
 *
 * Deliberately a separate event from AviatorRoundSettled rather than an
 * extra field on it — that one is ShouldBroadcastNow and runs synchronously
 * inside the game-loop worker specifically to reveal the server seed
 * promptly; this one queries every bet for the round, so it stays queued
 * like the other feed.* events instead of adding latency to that path.
 */
class AviatorRoundResults implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AviatorRound $round
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('aviator.rounds')];
    }

    public function broadcastAs(): string
    {
        return 'round.results';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round_number' => $this->round->round_number,
            'players' => AviatorBet::query()
                ->where('round_id', $this->round->id)
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
                ->values()
                ->all(),
        ];
    }
}
