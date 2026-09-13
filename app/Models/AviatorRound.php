<?php

namespace App\Models;

use App\Enums\RoundStatus;
use Database\Factories\AviatorRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $round_number
 * @property RoundStatus $status
 * @property string|null $server_seed
 * @property string $server_seed_hash
 * @property string $client_seed
 * @property int $nonce
 * @property float $house_edge
 * @property float|null $crash_multiplier
 * @property float $max_multiplier_cap
 * @property Carbon|null $started_at
 * @property Carbon|null $betting_closes_at
 * @property Carbon|null $crashed_at
 * @property Carbon|null $settled_at
 */
#[Fillable(['round_number', 'status', 'server_seed', 'server_seed_hash', 'client_seed', 'nonce', 'house_edge', 'crash_multiplier', 'max_multiplier_cap'])]
#[Hidden(['server_seed'])]
class AviatorRound extends Model
{
    /** @use HasFactory<AviatorRoundFactory> */
    use HasFactory;

    protected $table = 'game_rounds';

    protected function casts(): array
    {
        return [
            'status' => RoundStatus::class,
            'house_edge' => 'decimal:4',
            'crash_multiplier' => 'decimal:2',
            'max_multiplier_cap' => 'decimal:2',
            'started_at' => 'datetime',
            'betting_closes_at' => 'datetime',
            'crashed_at' => 'datetime',
            'settled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AviatorBet, $this>
     */
    public function bets(): HasMany
    {
        return $this->hasMany(AviatorBet::class, 'round_id');
    }
}
