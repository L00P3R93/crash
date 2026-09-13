<?php

namespace App\Models;

use App\Enums\LadderChoice;
use Database\Factories\AviatorBetLadderStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bet_id
 * @property int $step_number
 * @property float $from_multiplier
 * @property float|null $to_multiplier
 * @property LadderChoice $choice
 * @property float|null $probability_shown
 * @property bool|null $survived
 * @property Carbon|null $resolved_at
 */
#[Fillable(['bet_id', 'step_number', 'from_multiplier', 'to_multiplier', 'choice', 'probability_shown', 'survived', 'resolved_at'])]
class AviatorBetLadderStep extends Model
{
    /** @use HasFactory<AviatorBetLadderStepFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'game_bet_ladder_steps';

    protected function casts(): array
    {
        return [
            'from_multiplier' => 'decimal:2',
            'to_multiplier' => 'decimal:2',
            'choice' => LadderChoice::class,
            'probability_shown' => 'decimal:4',
            'survived' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AviatorBet, $this>
     */
    public function bet(): BelongsTo
    {
        return $this->belongsTo(AviatorBet::class, 'bet_id');
    }
}
