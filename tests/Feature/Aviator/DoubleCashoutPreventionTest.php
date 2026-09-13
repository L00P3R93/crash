<?php

namespace Tests\Feature\Aviator;

use App\Domain\Aviator\CashoutService;
use App\Domain\Aviator\Exceptions\BetAlreadySettledException;
use App\Enums\BetStatus;
use App\Enums\RoundStatus;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoubleCashoutPreventionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_second_cashout_attempt_on_the_same_bet_is_rejected(): void
    {
        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);
        $round = AviatorRound::factory()->create([
            'status' => RoundStatus::Running,
            'crash_multiplier' => 4.00,
            'started_at' => now()->subSeconds(5),
        ]);
        $bet = AviatorBet::factory()->create([
            'round_id' => $round->id,
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'stake' => 100,
            'status' => BetStatus::Active,
        ]);

        $service = app(CashoutService::class);

        $service->cashOut($bet->id, atMultiplier: 2.00);
        $balanceAfterFirstCashout = $wallet->fresh()->balance;
        $transactionCountAfterFirstCashout = $wallet->transactions()->count();

        $this->expectException(BetAlreadySettledException::class);

        try {
            $service->cashOut($bet->id, atMultiplier: 3.00);
        } finally {
            $this->assertSame($balanceAfterFirstCashout, $wallet->fresh()->balance);
            $this->assertSame($transactionCountAfterFirstCashout, $wallet->transactions()->count());
            $this->assertSame(BetStatus::Won, $bet->fresh()->status);
            $this->assertSame('2.00', $bet->fresh()->cashout_multiplier);
        }
    }
}
