<?php

namespace App\Models;

use App\Enums\UssdSessionStatus;
use Database\Factories\UssdSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $session_id
 * @property string $msisdn
 * @property string $service_code
 * @property string|null $network_code
 * @property int|null $player_id
 * @property string $current_screen
 * @property array<string, mixed>|null $state
 * @property UssdSessionStatus $status
 * @property string|null $last_input
 * @property Carbon|null $started_at
 * @property Carbon|null $last_interaction_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['session_id', 'msisdn', 'service_code', 'network_code', 'player_id', 'current_screen', 'state', 'status', 'last_input', 'started_at', 'last_interaction_at'])]
class UssdSession extends Model
{
    /** @use HasFactory<UssdSessionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'status' => UssdSessionStatus::class,
            'started_at' => 'datetime',
            'last_interaction_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Player, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
