<?php

namespace Tests\Feature\Aviator;

use App\Domain\Aviator\BetService;
use App\Domain\Aviator\Exceptions\InvalidStakeException;
use App\Domain\Aviator\Exceptions\RoundClosedException;
use App\Domain\Aviator\Exceptions\SelfExcludedException;
use App\Domain\Aviator\RoundService;
use App\Enums\BetStatus;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BetServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_placing_a_bet_debits_the_wallet_and_activates_the_bet(): void
    {
        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $bet = app(BetService::class)->placeBet($player, 100, 'ussd', 'BET-REF-1');

        $this->assertSame(BetStatus::Active, $bet->status);
        $this->assertSame('900.00', $player->wallet->fresh()->balance);
    }

    public function test_placing_a_bet_with_no_open_round_throws(): void
    {
        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $this->expectException(RoundClosedException::class);

        app(BetService::class)->placeBet($player, 100, 'ussd', 'BET-REF-NO-ROUND');
    }

    public function test_stake_outside_configured_bounds_throws(): void
    {
        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $this->expectException(InvalidStakeException::class);

        app(BetService::class)->placeBet($player, 1, 'ussd', 'BET-REF-TOO-SMALL');
    }

    public function test_self_excluded_player_cannot_place_a_bet(): void
    {
        $player = Player::factory()->create(['self_excluded_until' => now()->addDay()]);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $this->expectException(SelfExcludedException::class);

        app(BetService::class)->placeBet($player, 100, 'ussd', 'BET-REF-EXCLUDED');
    }

    public function test_replaying_the_same_bet_reference_does_not_debit_twice(): void
    {
        $player = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        $service = app(BetService::class);
        $first = $service->placeBet($player, 100, 'ussd', 'BET-REF-REPLAY');
        $second = $service->placeBet($player, 100, 'ussd', 'BET-REF-REPLAY');

        $this->assertSame($first->id, $second->id);
        $this->assertSame('900.00', $player->wallet->fresh()->balance);
    }
}
