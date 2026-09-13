<?php

namespace Tests\Feature\Ussd;

use App\Domain\Aviator\RoundService;
use App\Enums\BetStatus;
use App\Enums\UssdSessionStatus;
use App\Models\AviatorBet;
use App\Models\Player;
use App\Models\UssdSession;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SessionExpiryTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    public function test_a_stale_active_session_is_expired_by_the_prune_command(): void
    {
        config(['africastalking.session_ttl_seconds' => 180]);
        Player::factory()->withPin('1111')->create(['msisdn' => '254730000001']);

        $this->ussd('stale-1', '254730000001', '');

        UssdSession::query()->where('session_id', 'stale-1')->update([
            'last_interaction_at' => Carbon::now()->subSeconds(300),
        ]);

        $this->artisan('ussd:prune-sessions')->assertSuccessful();

        $session = UssdSession::query()->where('session_id', 'stale-1')->sole();
        $this->assertSame(UssdSessionStatus::Expired, $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_a_recently_active_session_is_left_alone(): void
    {
        Player::factory()->withPin('1111')->create(['msisdn' => '254730000002']);

        $this->ussd('fresh-1', '254730000002', '');

        $this->artisan('ussd:prune-sessions')->assertSuccessful();

        $session = UssdSession::query()->where('session_id', 'fresh-1')->sole();
        $this->assertSame(UssdSessionStatus::Active, $session->status);
    }

    public function test_an_abandoned_mid_ladder_bet_still_resolves_when_the_round_settles(): void
    {
        // No special handling needed: a bet stays `active` regardless of the
        // USSD session's fate, and RoundService::settleRound() already
        // resolves it against the real crash_multiplier either way.
        $rounds = app(RoundService::class);
        $round = $rounds->createRound();
        $round->forceFill(['crash_multiplier' => 2.00])->save();
        $rounds->openBetting($round);

        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254730000003']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $this->ussd('abandon-1', '254730000003', '');
        $this->ussd('abandon-1', '254730000003', '1');
        $this->ussd('abandon-1', '254730000003', '1*100');

        // Session "times out" here — player never sends another request.
        $rounds->startRound($round->fresh());
        $rounds->crashRound($round->fresh());
        $rounds->settleRound($round->fresh());

        $bet = AviatorBet::query()->where('player_id', $player->id)->sole();
        $this->assertSame(BetStatus::Lost, $bet->status);
    }
}
