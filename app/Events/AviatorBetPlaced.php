<?php

namespace App\Events;

use App\Models\AviatorBet;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Lets a player's other open tabs/devices (or a companion USSD session) see
 * a bet placed from elsewhere — private, since stake amounts are personal.
 *
 * Queued (not `ShouldBroadcastNow`) — fires from BetService inside an HTTP
 * request, and the tab that placed the bet already applies the REST
 * response body directly rather than waiting for this. See
 * AviatorBetCashedOut's docblock for the full reasoning.
 */
class AviatorBetPlaced implements ShouldBroadcast
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
        return 'bet.placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'bet_reference' => $this->bet->bet_reference,
            'round_id' => $this->bet->round_id,
            // Lets a client that has already moved on to a later round (this
            // one crashed and settled before the broadcast arrived — entirely
            // possible on a fast, low-multiplier round) recognize and discard
            // a stale event instead of resurrecting a dead bet's UI.
            'round_number' => $this->bet->round->round_number,
            'stake' => (float) $this->bet->stake,
            'auto_cashout' => $this->bet->auto_cashout === null ? null : (float) $this->bet->auto_cashout,
        ];
    }
}
