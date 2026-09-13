<?php

namespace App\Events;

use App\Models\AviatorRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The server-seed hash is published here, before anyone has bet — the
 * provably-fair commitment (architecture doc §6).
 */
class AviatorBettingOpened implements ShouldBroadcastNow
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
        return 'round.betting_opened';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round_number' => $this->round->round_number,
            'betting_closes_at' => $this->round->betting_closes_at?->toIso8601String(),
            'server_seed_hash' => $this->round->server_seed_hash,
        ];
    }
}
