<?php

namespace App\Models;

use Database\Factories\GameSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A single-row table of runtime-editable overrides for config/aviator.php.
 * A null column falls back to the .env-driven config default — see
 * AppServiceProvider::overlayGameSettings(), which is the only place these
 * values ever reach the rest of the app (every domain service still just
 * calls config('aviator.*') as before).
 *
 * @property int $id
 * @property float|null $house_edge
 * @property float|null $min_stake
 * @property float|null $max_stake
 * @property float|null $max_multiplier
 * @property int|null $betting_window_seconds
 * @property int|null $post_round_pause_seconds
 * @property float|null $acceleration_k
 * @property float|null $winnings_tax_rate
 */
#[Fillable(['house_edge', 'min_stake', 'max_stake', 'max_multiplier', 'betting_window_seconds', 'post_round_pause_seconds', 'acceleration_k', 'winnings_tax_rate'])]
class GameSetting extends Model
{
    /** @use HasFactory<GameSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'house_edge' => 'decimal:4',
            'min_stake' => 'decimal:2',
            'max_stake' => 'decimal:2',
            'max_multiplier' => 'decimal:2',
            'acceleration_k' => 'decimal:4',
            'winnings_tax_rate' => 'decimal:4',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }
}
