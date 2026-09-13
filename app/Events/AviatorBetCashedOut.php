<?php

namespace App\Events;

use App\Models\AviatorBet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirms a cash-out (manual or auto) to the player's own channel — the
 * payout figure here is already the server-computed, authoritative one
 * (architecture doc §10), never a client-supplied guess.
 *
 * Queued (not `ShouldBroadcastNow`) — unlike the round-lifecycle events in
 * RoundService, this fires from CashoutService inside an HTTP request, and
 * the manual web cash-out flow never waits on it (play.js applies the REST
 * response body directly). Broadcasting it synchronously was blocking the
 * HTTP response on a network round trip to Reverb the caller didn't need.
 */
class AviatorBetCashedOut implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AviatorBet $bet
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("aviator.player.{$this->bet->player_id}")];
    }

    public function broadcastAs(): string
    {
        return 'bet.cashed_out';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'bet_reference' => $this->bet->bet_reference,
            'cashout_multiplier' => (float) $this->bet->cashout_multiplier,
            'payout' => (float) $this->bet->payout,
        ];
    }
}
