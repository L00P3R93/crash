<?php

namespace Tests\Feature\Ussd;

use App\Domain\Aviator\RoundService;
use App\Enums\BetStatus;
use App\Models\AviatorBet;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BetEntryTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    private function openRound(): void
    {
        $rounds = app(RoundService::class);
        $round = $rounds->createRound();
        $round->forceFill(['crash_multiplier' => 4.73])->save();
        $rounds->openBetting($round);
    }

    public function test_a_stake_below_the_minimum_shows_a_validation_error(): void
    {
        $this->openRound();
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254711111111']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $this->ussd('bet-1', '254711111111', '1');
        $response = $this->ussd('bet-1', '254711111111', '1*5');

        $response->assertSee('Minimum bet is KSh 10.00.', false);
    }

    public function test_a_stake_above_the_maximum_shows_a_validation_error(): void
    {
        $this->openRound();
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254711111112']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 100000]);

        $this->ussd('bet-2', '254711111112', '1');
        $response = $this->ussd('bet-2', '254711111112', '1*5000');

        $response->assertSee('Maximum bet is KSh 2,000.00.', false);
    }

    public function test_a_stake_exceeding_the_balance_shows_insufficient_balance(): void
    {
        $this->openRound();
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254711111113']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 50]);

        $this->ussd('bet-3', '254711111113', '1');
        $response = $this->ussd('bet-3', '254711111113', '1*100');

        $response->assertSee('Insufficient balance.', false);
        $response->assertSee('2: Top Up', false);
    }

    public function test_a_valid_stake_places_the_bet_and_shows_the_first_checkpoint(): void
    {
        $this->openRound();
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254711111114']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $this->ussd('bet-4', '254711111114', '1');
        $response = $this->ussd('bet-4', '254711111114', '1*100');

        $response->assertSee('Stake KSh 100.00 | Now 1.00x', false);
        $response->assertSee('2: Continue to', false);
        $response->assertSee('3: Rocket to', false);

        $bet = AviatorBet::query()->where('player_id', $player->id)->sole();
        $this->assertSame(BetStatus::Active, $bet->status);
        $this->assertSame('900.00', $player->wallet->fresh()->balance);
    }

    public function test_betting_with_no_open_round_shows_a_message(): void
    {
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254711111115']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $this->ussd('bet-5', '254711111115', '1');
        $response = $this->ussd('bet-5', '254711111115', '1*100');

        $response->assertSee('No round is open for betting right now.', false);
    }
}
