<?php

namespace Tests\Feature\Api;

use App\Domain\Aviator\BetService;
use App\Domain\Aviator\RoundService;
use App\Models\AviatorBet;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AviatorBetControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedPlayer(float $balance = 1000): Player
    {
        $player = Player::factory()->withPin('4321')->create();
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => $balance]);

        $token = $this->postJson('/api/auth/login', [
            'msisdn' => $player->msisdn,
            'pin' => '4321',
        ])->json('token');

        $this->withToken($token);

        return $player;
    }

    public function test_placing_a_bet_requires_authentication(): void
    {
        $this->postJson('/api/aviator/bets', ['stake' => 100])->assertUnauthorized();
    }

    public function test_a_player_can_place_a_bet_on_an_open_round(): void
    {
        $player = $this->authenticatedPlayer();
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $response = $this->postJson('/api/aviator/bets', ['stake' => 100]);

        $response->assertCreated()
            ->assertJsonPath('data.stake', 100)
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('900.00', $player->wallet->fresh()->balance);
    }

    public function test_placing_a_bet_with_no_open_round_returns_422(): void
    {
        $this->authenticatedPlayer();

        $this->postJson('/api/aviator/bets', ['stake' => 100])->assertUnprocessable();
    }

    public function test_repeating_the_same_idempotency_key_does_not_double_charge(): void
    {
        $player = $this->authenticatedPlayer();
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $headers = ['Idempotency-Key' => 'retry-key-1'];

        $first = $this->withHeaders($headers)->postJson('/api/aviator/bets', ['stake' => 100]);
        $second = $this->withHeaders($headers)->postJson('/api/aviator/bets', ['stake' => 100]);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('data.bet_reference'), $second->json('data.bet_reference'));
        $this->assertSame('900.00', $player->wallet->fresh()->balance);
    }

    public function test_a_player_can_cash_out_an_active_bet(): void
    {
        $this->authenticatedPlayer();
        $round = app(RoundService::class)->createRound();
        // Force a high crash point so cashing out immediately after start is
        // never flaky against whatever the RNG happened to draw.
        $round->forceFill(['crash_multiplier' => 100])->save();
        app(RoundService::class)->openBetting($round);

        $bet = $this->postJson('/api/aviator/bets', ['stake' => 100])->json('data');
        app(RoundService::class)->startRound($round->fresh());

        $betId = AviatorBet::where('bet_reference', $bet['bet_reference'])->first()->id;

        $response = $this->postJson("/api/aviator/bets/{$betId}/cashout");

        $response->assertOk()->assertJsonPath('data.status', 'won');
    }

    public function test_a_player_cannot_see_or_cash_out_another_players_bet(): void
    {
        $owner = Player::factory()->withPin('1111')->create();
        Wallet::factory()->create(['player_id' => $owner->id, 'balance' => 1000]);
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        // Sanctum::actingAs sets the guard's resolved user directly, rather
        // than relying on a header token — the `RequestGuard` Sanctum uses
        // caches its resolved user for its lifetime, so switching identities
        // mid-test via two different `withToken()` calls would silently
        // still authenticate as whichever player resolved first.
        Sanctum::actingAs($owner);
        $bet = $this->postJson('/api/aviator/bets', ['stake' => 100])->json('data');
        $betId = AviatorBet::where('bet_reference', $bet['bet_reference'])->first()->id;

        $intruder = Player::factory()->withPin('4321')->create();
        Wallet::factory()->create(['player_id' => $intruder->id, 'balance' => 1000]);
        Sanctum::actingAs($intruder);

        $this->getJson("/api/aviator/bets/{$betId}")->assertNotFound();
        $this->postJson("/api/aviator/bets/{$betId}/cashout")->assertNotFound();
    }

    public function test_active_bet_returns_the_players_current_bet_for_reload_recovery(): void
    {
        $this->authenticatedPlayer();
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $bet = $this->postJson('/api/aviator/bets', ['stake' => 100])->json('data');

        $response = $this->getJson('/api/aviator/bets/active');

        $response->assertOk()
            ->assertJsonPath('data.bet_reference', $bet['bet_reference'])
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.round_number', $round->round_number);
    }

    public function test_active_bet_never_leaks_the_running_rounds_crash_multiplier(): void
    {
        $this->authenticatedPlayer();
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);
        $this->postJson('/api/aviator/bets', ['stake' => 100]);
        app(RoundService::class)->startRound($round->fresh());

        $response = $this->getJson('/api/aviator/bets/active');

        $response->assertOk()->assertJsonPath('data.round_crash_multiplier', null);
    }

    public function test_active_bet_is_null_when_the_player_has_no_bet_in_flight(): void
    {
        $this->authenticatedPlayer();

        $this->getJson('/api/aviator/bets/active')->assertOk()->assertJsonPath('data', null);
    }

    public function test_a_lost_bets_round_crash_multiplier_is_still_exposed_once_settled(): void
    {
        $this->authenticatedPlayer();
        $round = app(RoundService::class)->createRound();
        $round->forceFill(['crash_multiplier' => 2])->save();
        app(RoundService::class)->openBetting($round);

        $bet = $this->postJson('/api/aviator/bets', ['stake' => 100])->json('data');
        app(RoundService::class)->startRound($round->fresh());
        app(RoundService::class)->crashRound($round->fresh());
        app(RoundService::class)->settleRound($round->fresh());

        $betId = AviatorBet::where('bet_reference', $bet['bet_reference'])->first()->id;

        $response = $this->getJson("/api/aviator/bets/{$betId}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'lost')
            ->assertJsonPath('data.round_crash_multiplier', 2);
    }

    public function test_recent_bets_only_returns_the_authenticated_players_own_resolved_bets(): void
    {
        $this->authenticatedPlayer();
        $round = app(RoundService::class)->createRound();
        $round->forceFill(['crash_multiplier' => 100])->save();
        app(RoundService::class)->openBetting($round);

        $mine = $this->postJson('/api/aviator/bets', ['stake' => 50])->json('data');

        $someoneElse = Player::factory()->withPin('9999')->create();
        Wallet::factory()->create(['player_id' => $someoneElse->id, 'balance' => 1000]);
        $theirs = app(BetService::class)->placeBet($someoneElse, 20, 'web', 'OTHER-PLAYER-BET');

        app(RoundService::class)->startRound($round->fresh());
        $mineId = AviatorBet::where('bet_reference', $mine['bet_reference'])->first()->id;
        $this->postJson("/api/aviator/bets/{$mineId}/cashout");

        $response = $this->getJson('/api/aviator/bets/recent');

        $response->assertOk();
        $bets = collect($response->json('data'));
        $this->assertTrue($bets->every(fn ($bet) => $bet['bet_reference'] !== $theirs->bet_reference));
        $this->assertTrue($bets->contains(fn ($bet) => $bet['bet_reference'] === $mine['bet_reference']));
    }
}
