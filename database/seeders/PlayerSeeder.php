<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

/**
 * A specific test/demo player: 254795702455, PIN 1234, wallet pre-loaded
 * with KSh 10,000 — enough to log in to /play (or dial the USSD code) and
 * exercise betting without going through a real M-Pesa top-up first.
 *
 * Safe to re-run: finds the existing player/wallet by msisdn rather than
 * inserting a duplicate (msisdn is unique) or resetting a balance that's
 * since changed through actual play.
 *
 * Run explicitly, not part of the default `db:seed` chain — see
 * DatabaseSeeder for the seeders that do run automatically.
 *
 *     php artisan db:seed --class=PlayerSeeder
 */
class PlayerSeeder extends Seeder
{
    private const MSISDN = '254795702455';

    private const PIN = '1234';

    private const INITIAL_BALANCE = 10000;

    public function run(): void
    {
        $player = Player::query()->where('msisdn', self::MSISDN)->first();

        if (! $player) {
            $player = Player::factory()->withPin(self::PIN)->create([
                'msisdn' => self::MSISDN,
                'name' => null,
                'registered_via' => 'web',
            ]);
        }

        if ($player->wallet) {
            return;
        }

        Wallet::factory()->create([
            'player_id' => $player->id,
            'balance' => self::INITIAL_BALANCE,
        ]);
    }
}
