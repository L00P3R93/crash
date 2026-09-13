<?php

namespace App\Http\Resources;

use App\Enums\BetStatus;
use App\Models\AviatorBet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AviatorBet
 */
class AviatorBetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Gated on the BET's own resolution, not merely on the round relation
        // being loaded: `GET aviator/bets/active` loads the round too, but
        // for a still-active bet that round hasn't crashed yet. Exposing its
        // crash point here would leak it before the reveal (architecture
        // doc §25's "the crash point is only revealed at that instant").
        $resolved = in_array($this->status, [BetStatus::Won, BetStatus::Lost], true);

        return [
            'id' => $this->id,
            'bet_reference' => $this->bet_reference,
            'round_number' => $this->whenLoaded('round', fn () => $this->round->round_number),
            'round_crash_multiplier' => $resolved
                ? $this->whenLoaded('round', fn () => (float) $this->round->crash_multiplier)
                : null,
            'channel' => $this->channel,
            'stake' => (float) $this->stake,
            'auto_cashout' => $this->auto_cashout === null ? null : (float) $this->auto_cashout,
            'cashout_multiplier' => $this->cashout_multiplier === null ? null : (float) $this->cashout_multiplier,
            'payout' => $this->payout === null ? null : (float) $this->payout,
            'status' => $this->status->value,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'cashed_out_at' => $this->cashed_out_at?->toIso8601String(),
        ];
    }
}
