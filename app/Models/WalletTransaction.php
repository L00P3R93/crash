<?php

namespace App\Models;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $wallet_id
 * @property string $reference
 * @property WalletTransactionType $type
 * @property float $amount
 * @property float $balance_before
 * @property float $balance_after
 * @property WalletTransactionStatus $status
 * @property string|null $related_type
 * @property int|null $related_id
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['wallet_id', 'reference', 'type', 'amount', 'balance_before', 'balance_after', 'status', 'metadata'])]
class WalletTransaction extends EloquentModel
{
    /** @use HasFactory<WalletTransactionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'status' => WalletTransactionStatus::class,
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Wallet, $this>
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * @return MorphTo<EloquentModel, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
