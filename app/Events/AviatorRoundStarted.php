<?php

namespace App\Events;

use App\Models\AviatorRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The multiplier begins climbing from this instant. The browser computes
 * M(t) = e^(k*t) itself from `started_at` — the crash point is never sent
 * here (architecture doc §8/§25).
 */
class AviatorRoundStarted implements ShouldBroadcastNow
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
        return 'round.started';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round_number' => $this->round->round_number,
            'started_at' => $this->round->started_at?->toIso8601String(),
            // Sent here too (not just via a separate config fetch) so the
            // client can start animating M(t) = e^(k*t) the instant this
            // event arrives, with everything it needs already in hand.
            'acceleration_k' => (float) config('aviator.acceleration_k'),
        ];
    }
}
