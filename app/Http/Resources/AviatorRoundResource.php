<?php

namespace App\Http\Resources;

use App\Enums\RoundStatus;
use App\Models\AviatorRound;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AviatorRound
 *
 * Never exposes `crash_multiplier` (or the server seed) before the round has
 * actually crashed — the browser must not learn the outcome early just
 * because it asked for round state (architecture doc §25). Betting/running
 * rounds reveal only what a client legitimately needs to render the UI and
 * compute the live multiplier itself.
 */
class AviatorRoundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $crashRevealed = in_array($this->status, [RoundStatus::Crashed, RoundStatus::Settled], true);

        return [
            'round_number' => $this->round_number,
            'status' => $this->status->value,
            'server_seed_hash' => $this->server_seed_hash,
            'betting_closes_at' => $this->betting_closes_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'acceleration_k' => (float) config('aviator.acceleration_k'),
            'crash_multiplier' => $crashRevealed ? (float) $this->crash_multiplier : null,
            'crashed_at' => $this->crashed_at?->toIso8601String(),
            'server_seed' => $this->status === RoundStatus::Settled ? $this->server_seed : null,
            'client_seed' => $this->status === RoundStatus::Settled ? $this->client_seed : null,
            'nonce' => $this->status === RoundStatus::Settled ? $this->nonce : null,
        ];
    }
}
