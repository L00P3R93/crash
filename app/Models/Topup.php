<?php

namespace App\Models;

use App\Enums\TopupProvider;
use App\Enums\TopupStatus;
use Database\Factories\TopupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $player_id
 * @property int $wallet_id
 * @property float $amount
 * @property TopupProvider $provider
 * @property string|null $provider_reference
 * @property TopupStatus $status
 * @property int|null $wallet_transaction_id
 * @property Carbon|null $requested_at
 * @property Carbon|null $completed_at
 */
#[Fillable(['player_id', 'wallet_id', 'amount', 'provider', 'provider_reference', 'status', 'requested_at'])]
class Topup extends Model
{
    /** @use HasFactory<TopupFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider' => TopupProvider::class,
            'status' => TopupStatus::class,
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
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
     * @return BelongsTo<WalletTransaction, $this>
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }
}
