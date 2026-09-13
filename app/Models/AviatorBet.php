<?php

namespace App\Models;

use App\Enums\BetStatus;
use Database\Factories\AviatorBetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $round_id
 * @property int $player_id
 * @property int $wallet_id
 * @property string $bet_reference
 * @property string $channel
 * @property float $stake
 * @property float $current_rung
 * @property float|null $auto_cashout
 * @property float|null $cashout_multiplier
 * @property float|null $payout
 * @property BetStatus $status
 * @property Carbon|null $placed_at
 * @property Carbon|null $cashed_out_at
 */
#[Fillable(['round_id', 'player_id', 'wallet_id', 'bet_reference', 'channel', 'stake', 'auto_cashout', 'status', 'placed_at'])]
class AviatorBet extends Model
{
    /** @use HasFactory<AviatorBetFactory> */
    use HasFactory;

    protected $table = 'game_bets';

    protected function casts(): array
    {
        return [
            'stake' => 'decimal:2',
            'current_rung' => 'decimal:2',
            'auto_cashout' => 'decimal:2',
            'cashout_multiplier' => 'decimal:2',
            'payout' => 'decimal:2',
            'status' => BetStatus::class,
            'placed_at' => 'datetime',
            'cashed_out_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AviatorRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(AviatorRound::class, 'round_id');
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return HasMany<AviatorBetLadderStep, $this>
     */
    public function ladderSteps(): HasMany
    {
        return $this->hasMany(AviatorBetLadderStep::class, 'bet_id');
    }
}
