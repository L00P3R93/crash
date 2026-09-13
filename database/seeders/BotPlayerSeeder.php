<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

/**
 * 100 synthetic "bot" players used only to keep local/dev rounds looking
 * busy — App\Listeners\PlaceBotBets picks a random subset of these every
 * round to place a real bet with a random auto-cashout target, so the new
 * "active players this round" table always has activity to show.
 *
 * Deterministic, clearly-reserved msisdns (254700000001-254700000100) so
 * they're easy to spot/filter/clean up and never collide with faker-
 * generated numbers used elsewhere. Wallets are pre-loaded with a very
 * large balance (they never need topping up) and `is_bot = true`, which
 * PlayerLimitsGuard::assertCanWithdraw() uses to block real-money
 * withdrawals for these accounts.
 *
 * Safe to re-run: finds each player by msisdn rather than duplicating, and
 * only creates a wallet if one doesn't already exist. Refuses to run at all
 * outside local/testing as a fail-safe against accidentally seeding 100
 * fake accounts into a real environment.
 *
 * Run explicitly, not part of the default `db:seed` chain — see
 * DatabaseSeeder for the seeders that do run automatically.
 *
 *     php artisan db:seed --class=BotPlayerSeeder
 */
class BotPlayerSeeder extends Seeder
{
    private const COUNT = 100;

    private const MSISDN_PREFIX = '254700000';

    private const PIN = '0000';

    private const INITIAL_BALANCE = 1_000_000;

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        for ($i = 1; $i <= self::COUNT; $i++) {
            $msisdn = self::MSISDN_PREFIX.str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $player = Player::query()->where('msisdn', $msisdn)->first();

            if (! $player) {
                $player = Player::factory()->withPin(self::PIN)->create([
                    'msisdn' => $msisdn,
                    'name' => "Bot {$i}",
                    'registered_via' => 'bot',
                    'is_bot' => true,
                ]);
            }

            if ($player->wallet) {
                continue;
            }

            Wallet::factory()->create([
                'player_id' => $player->id,
                'balance' => self::INITIAL_BALANCE,
            ]);
        }
    }
}
