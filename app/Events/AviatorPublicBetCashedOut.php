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
 * Public counterpart to AviatorBetCashedOut's private channel — see
 * AviatorPublicBetPlaced for why this is a separate event rather than a
 * second channel on the private one.
 *
 * Queued — see AviatorBetCashedOut's docblock; same reasoning applies here.
 */
class AviatorPublicBetCashedOut implements ShouldBroadcast
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
        return 'feed.bet_cashed_out';
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
            'cashout_multiplier' => (float) $this->bet->cashout_multiplier,
            'payout' => (float) $this->bet->payout,
        ];
    }
}
