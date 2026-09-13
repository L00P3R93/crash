<?php

namespace Tests\Feature\Aviator;

use App\Domain\Aviator\BetService;
use App\Domain\Aviator\CashoutService;
use App\Domain\Aviator\RoundService;
use App\Events\AviatorBetCashedOut;
use App\Events\AviatorBetPlaced;
use App\Events\AviatorBettingOpened;
use App\Events\AviatorPublicBetCashedOut;
use App\Events\AviatorPublicBetPlaced;
use App\Events\AviatorRoundSettled;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * The Phase 6 web/WebSocket leg's broadcast events — round-lifecycle events
 * already covered by RoundLifecycleTest, this covers the ones this phase
 * added: the fairness-commitment/reveal pair and the private per-player
 * bet confirmations.
 */
class BroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_betting_broadcasts_the_seed_hash_commitment(): void
    {
        Event::fake([AviatorBettingOpened::class]);

        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        Event::assertDispatched(AviatorBettingOpened::class, function (AviatorBettingOpened $event) use ($round) {
            return $event->round->id === $round->id
                && $event->broadcastWith()['server_seed_hash'] === $round->server_seed_hash;
        });
    }

    public function test_settling_a_round_broadcasts_the_fairness_reveal(): void
    {
        Event::fake([AviatorRoundSettled::class]);

        $rounds = app(RoundService::class);
        $round = $rounds->createRound();
        $round = $rounds->openBetting($round);
        $round = $rounds->startRound($round->fresh());
        $round = $rounds->crashRound($round);
        $rounds->settleRound($round);

        Event::assertDispatched(AviatorRoundSettled::class, function (AviatorRoundSettled $event) use ($round) {
            $payload = $event->broadcastWith();

            return $payload['server_seed'] === $round->fresh()->server_seed
                && $payload['crash_multiplier'] === (float) $round->crash_multiplier;
        });
    }

    public function test_placing_a_bet_broadcasts_to_the_players_own_private_channel(): void
    {
        Event::fake([AviatorBetPlaced::class]);

        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $bet = app(BetService::class)->placeBet($player, 100, 'web', 'BROADCAST-BET-1');

        Event::assertDispatched(AviatorBetPlaced::class, function (AviatorBetPlaced $event) use ($bet, $round, $player) {
            $channels = $event->broadcastOn();

            return $event->bet->id === $bet->id
                && $event->broadcastWith()['round_number'] === $round->round_number
                && $channels[0]->name === "private-aviator.player.{$player->id}";
        });
    }

    public function test_cashing_out_broadcasts_the_payout_to_the_players_own_private_channel(): void
    {
        Event::fake([AviatorBetCashedOut::class]);

        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $rounds = app(RoundService::class);
        $round = $rounds->createRound();
        // Force a high crash point so the atMultiplier:2.00 cashout below is
        // never flaky against whatever the RNG happened to draw.
        $round->forceFill(['crash_multiplier' => 100])->save();
        $round = $rounds->openBetting($round);
        $bet = app(BetService::class)->placeBet($player, 100, 'web', 'BROADCAST-BET-2');
        $rounds->startRound($round->fresh());

        $bet = app(CashoutService::class)->cashOut($bet->id, atMultiplier: 2.00);

        Event::assertDispatched(AviatorBetCashedOut::class, function (AviatorBetCashedOut $event) use ($bet, $player) {
            $payload = $event->broadcastWith();
            $channels = $event->broadcastOn();

            return $payload['payout'] === (float) $bet->payout
                && $channels[0]->name === "private-aviator.player.{$player->id}";
        });
    }

    public function test_placing_a_bet_also_broadcasts_a_masked_version_to_the_public_live_feed(): void
    {
        Event::fake([AviatorPublicBetPlaced::class]);

        $player = Player::factory()->create(['msisdn' => '254712345678']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        app(BetService::class)->placeBet($player, 100, 'web', 'BROADCAST-BET-3');

        Event::assertDispatched(AviatorPublicBetPlaced::class, function (AviatorPublicBetPlaced $event) use ($round) {
            $payload = $event->broadcastWith();
            $channels = $event->broadcastOn();

            return $payload['round_number'] === $round->round_number
                && $payload['msisdn'] === '254712***678'
                && $payload['stake'] === 100.0
                && $channels[0]->name === 'aviator.rounds';
        });
    }

    public function test_cashing_out_also_broadcasts_a_masked_version_to_the_public_live_feed(): void
    {
        Event::fake([AviatorPublicBetCashedOut::class]);

        $player = Player::factory()->create(['msisdn' => '254712345678']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $rounds = app(RoundService::class);
        $round = $rounds->createRound();
        $round->forceFill(['crash_multiplier' => 100])->save();
        $round = $rounds->openBetting($round);
        $bet = app(BetService::class)->placeBet($player, 100, 'web', 'BROADCAST-BET-4');
        $rounds->startRound($round->fresh());

        app(CashoutService::class)->cashOut($bet->id, atMultiplier: 2.00);

        Event::assertDispatched(AviatorPublicBetCashedOut::class, function (AviatorPublicBetCashedOut $event) {
            $payload = $event->broadcastWith();
            $channels = $event->broadcastOn();

            return $payload['msisdn'] === '254712***678'
                && $payload['cashout_multiplier'] === 2.0
                && $channels[0]->name === 'aviator.rounds';
        });
    }
}
