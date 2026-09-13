<?php

namespace Tests\Feature\Api;

use App\Domain\Aviator\BetService;
use App\Domain\Aviator\RoundService;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AviatorRoundControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_round_yet_returns_null_data(): void
    {
        $this->getJson('/api/aviator/round')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_no_round_yet_players_endpoint_returns_empty_list(): void
    {
        $this->getJson('/api/aviator/round/players')
            ->assertOk()
            ->assertJson(['data' => ['round_number' => null, 'players' => []]]);
    }

    public function test_players_endpoint_lists_masked_bets_for_the_current_round(): void
    {
        $player = Player::factory()->create(['msisdn' => '254712345678']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);
        $bet = app(BetService::class)->placeBet($player, 100, 'web', 'TEST-REF-1');

        $response = $this->getJson('/api/aviator/round/players')->assertOk();

        $response->assertJsonPath('data.round_number', $round->round_number);
        $response->assertJsonPath('data.players.0.bet_id', $bet->id);
        $response->assertJsonPath('data.players.0.msisdn', '254712***678');
        $response->assertJsonPath('data.players.0.stake', 100);
        $response->assertJsonPath('data.players.0.status', 'active');
    }

    public function test_a_betting_round_never_reveals_the_crash_multiplier_or_seed(): void
    {
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $this->getJson('/api/aviator/round')
            ->assertOk()
            ->assertJsonPath('data.status', 'betting')
            ->assertJsonPath('data.crash_multiplier', null)
            ->assertJsonPath('data.server_seed', null)
            ->assertJsonMissing(['data' => ['server_seed' => $round->server_seed]]);
    }

    public function test_a_crashed_round_reveals_the_crash_multiplier_but_not_the_seed(): void
    {
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);
        $round = app(RoundService::class)->startRound($round->fresh());
        app(RoundService::class)->crashRound($round);

        $response = $this->getJson('/api/aviator/round')->assertOk();

        $response->assertJsonPath('data.status', 'crashed');
        $this->assertNotNull($response->json('data.crash_multiplier'));
        $this->assertNull($response->json('data.server_seed'));
    }

    public function test_a_settled_round_reveals_the_server_seed_for_verification(): void
    {
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);
        $round = app(RoundService::class)->startRound($round->fresh());
        $round = app(RoundService::class)->crashRound($round);
        app(RoundService::class)->settleRound($round);

        $response = $this->getJson('/api/aviator/round')->assertOk();

        $response->assertJsonPath('data.status', 'settled');
        $this->assertSame($round->fresh()->server_seed, $response->json('data.server_seed'));
    }
}
