<?php

namespace Tests\Feature\Aviator;

use App\Domain\Aviator\AviatorGameService;
use App\Domain\Aviator\BetService;
use App\Domain\Aviator\RoundService;
use App\Enums\BetStatus;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reproduces the worked example in architecture doc §24: a round crashing at
 * 4.73x, with an auto-cashout winner, a manual-cashout winner, and an
 * auto-cashout that never gets reached (a loss). Verifies gross payouts
 * against the doc's figures; net payouts additionally apply the withholding
 * tax added in this codebase (the doc predates that feature).
 */
class RoundOutcomesWorkedExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_players_a_b_and_c_resolve_exactly_as_in_the_worked_example(): void
    {
        config(['aviator.winnings_tax_rate' => 0.0]);

        $rounds = app(RoundService::class);
        $bets = app(BetService::class);
        $game = app(AviatorGameService::class);

        $playerA = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $playerA->id, 'balance' => 1000]);

        $playerB = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $playerB->id, 'balance' => 1000]);

        $playerC = Player::factory()->create();
        Wallet::factory()->create(['player_id' => $playerC->id, 'balance' => 1000]);

        $round = $rounds->createRound();
        $round->forceFill(['crash_multiplier' => 4.73])->save();
        $rounds->openBetting($round);

        $betA = $bets->placeBet($playerA, 100, 'ussd', 'WORKED-A', autoCashout: 2.00);
        $betB = $bets->placeBet($playerB, 500, 'web', 'WORKED-B');
        $betC = $bets->placeBet($playerC, 50, 'ussd', 'WORKED-C', autoCashout: 5.00);

        $rounds->startRound($round->fresh());

        // Player B manually cashes out mid-round, before the crash.
        $game->cashOut($betB->id, atMultiplier: 3.20);

        $rounds->crashRound($round->fresh()); // triggers ResolveAutoCashouts synchronously
        $rounds->settleRound($round->fresh());

        $betA->refresh();
        $betB->refresh();
        $betC->refresh();

        // Player A auto-cashed out at 2.00x: 100 x 2 = 200.
        $this->assertSame(BetStatus::Won, $betA->status);
        $this->assertSame('2.00', $betA->cashout_multiplier);
        $this->assertSame('200.00', $betA->payout);

        // Player B manually cashed out at 3.20x: 500 x 3.20 = 1600.
        $this->assertSame(BetStatus::Won, $betB->status);
        $this->assertSame('3.20', $betB->cashout_multiplier);
        $this->assertSame('1600.00', $betB->payout);

        // Player C's 5.00x target was never reached before the 4.73x crash.
        $this->assertSame(BetStatus::Lost, $betC->status);
        $this->assertSame('0.00', $betC->payout);

        $this->assertSame('1100.00', $playerA->wallet->fresh()->balance); // 1000 - 100 + 200
        $this->assertSame('2100.00', $playerB->wallet->fresh()->balance); // 1000 - 500 + 1600
        $this->assertSame('950.00', $playerC->wallet->fresh()->balance); // 1000 - 50
    }
}
