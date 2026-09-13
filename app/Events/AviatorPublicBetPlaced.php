<?php

namespace App\Events;

use App\Models\AviatorBet;
use App\Support\MsisdnMasker;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The public "live bet feed" — every player watching the round sees a masked
 * version of this, unlike AviatorBetPlaced's private per-player channel. Kept
 * as a separate event rather than a second channel on the private one so
 * neither payload has to conditionally hide fields depending on who's
 * listening.
 *
 * Queued — see AviatorBetCashedOut's docblock; same reasoning applies here.
 */
class AviatorPublicBetPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly AviatorBet $bet
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('aviator.rounds')];
    }

    public function broadcastAs(): string
    {
        return 'feed.bet_placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round_number' => $this->bet->round->round_number,
            'bet_id' => $this->bet->id,
            'msisdn' => MsisdnMasker::mask($this->bet->player->msisdn),
            'stake' => (float) $this->bet->stake,
        ];
    }
}
