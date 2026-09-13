<?php

namespace App\Events;

use App\Models\AviatorRound;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The server seed is revealed here so any client can reproduce the crash
 * point themselves (architecture doc §6) — the same fairness reveal the
 * Filament FairnessAudit page and USSD's FairnessVerifyScreen expose.
 */
class AviatorRoundSettled implements ShouldBroadcastNow
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
        return 'round.settled';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'round_number' => $this->round->round_number,
            'crash_multiplier' => (float) $this->round->crash_multiplier,
            'server_seed' => $this->round->server_seed,
            'server_seed_hash' => $this->round->server_seed_hash,
            'client_seed' => $this->round->client_seed,
            'nonce' => $this->round->nonce,
        ];
    }
}
