<?php

namespace App\Models;

use App\Enums\PlayerStatus;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $msisdn
 * @property string|null $name
 * @property string|null $pin_hash
 * @property Carbon|null $pin_set_at
 * @property PlayerStatus $status
 * @property string|null $registered_via
 * @property float|null $daily_deposit_limit
 * @property float|null $daily_stake_limit
 * @property Carbon|null $self_excluded_until
 * @property Carbon|null $last_seen_at
 * @property bool $is_bot
 */
#[Fillable(['msisdn', 'name', 'status', 'registered_via', 'is_bot'])]
#[Hidden(['pin_hash'])]
class Player extends Authenticatable
{
    /** @use HasFactory<PlayerFactory> */
    use HasApiTokens, HasFactory;

    /**
     * Web/API auth is msisdn + PIN, not the `password` column Authenticatable
     * expects — there is no such column on `players`. Login goes through
     * PlayerAuthController's manual Hash::check(pin, pin_hash) instead of
     * Auth::attempt(), so this is never actually read.
     */
    public function getAuthPassword(): string
    {
        return $this->pin_hash ?? '';
    }

    protected function casts(): array
    {
        return [
            'status' => PlayerStatus::class,
            'pin_set_at' => 'datetime',
            'daily_deposit_limit' => 'decimal:2',
            'daily_stake_limit' => 'decimal:2',
            'self_excluded_until' => 'datetime',
            'last_seen_at' => 'datetime',
            'is_bot' => 'boolean',
        ];
    }

    /**
     * @return HasOne<Wallet, $this>
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * @return HasMany<Topup, $this>
     */
    public function topups(): HasMany
    {
        return $this->hasMany(Topup::class);
    }

    /**
     * @return HasMany<AviatorBet, $this>
     */
    public function bets(): HasMany
    {
        return $this->hasMany(AviatorBet::class);
    }

    /**
     * @return HasMany<UssdSession, $this>
     */
    public function ussdSessions(): HasMany
    {
        return $this->hasMany(UssdSession::class);
    }

    public function isSelfExcluded(): bool
    {
        return $this->self_excluded_until !== null && $this->self_excluded_until->isFuture();
    }
}
