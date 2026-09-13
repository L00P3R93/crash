<?php

namespace Tests\Feature\Aviator;

use App\Domain\Aviator\CashoutService;
use App\Domain\Aviator\Exceptions\RoundClosedException;
use App\Enums\BetStatus;
use App\Enums\RoundStatus;
use App\Enums\WalletTransactionType;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_out_credits_the_net_payout_after_withholding_tax(): void
    {
        config(['aviator.winnings_tax_rate' => 0.20]);

        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);
        $round = AviatorRound::factory()->create([
            'status' => RoundStatus::Running,
            'crash_multiplier' => 4.73,
            'started_at' => now()->subSeconds(5),
        ]);
        $bet = AviatorBet::factory()->create([
            'round_id' => $round->id,
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'stake' => 500,
            'status' => BetStatus::Active,
        ]);

        $result = app(CashoutService::class)->cashOut($bet->id, atMultiplier: 3.20);

        $this->assertSame(BetStatus::Won, $result->status);
        $this->assertSame('3.20', $result->cashout_multiplier);
        // gross = 500 * 3.20 = 1600.00; tax = 20% = 320.00; net = 1280.00
        $this->assertSame('1280.00', $result->payout);
        $this->assertSame('1280.00', $wallet->fresh()->balance);

        $winTxn = $wallet->transactions()->where('type', WalletTransactionType::GameWin)->sole();
        $this->assertSame('1600.00', $winTxn->amount);

        $taxTxn = $wallet->transactions()->where('type', WalletTransactionType::WithholdingTax)->sole();
        $this->assertSame('-320.00', $taxTxn->amount);
    }

    public function test_cash_out_fails_once_the_multiplier_has_reached_the_crash_point(): void
    {
        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id]);
        $round = AviatorRound::factory()->create([
            'status' => RoundStatus::Running,
            'crash_multiplier' => 2.00,
            'started_at' => now()->subSeconds(5),
        ]);
        $bet = AviatorBet::factory()->create([
            'round_id' => $round->id,
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'status' => BetStatus::Active,
        ]);

        $this->expectException(RoundClosedException::class);

        app(CashoutService::class)->cashOut($bet->id, atMultiplier: 2.00);
    }

    public function test_no_tax_transaction_is_written_when_the_tax_rate_is_zero(): void
    {
        config(['aviator.winnings_tax_rate' => 0.0]);

        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);
        $round = AviatorRound::factory()->create([
            'status' => RoundStatus::Running,
            'crash_multiplier' => 5.00,
            'started_at' => now()->subSeconds(5),
        ]);
        $bet = AviatorBet::factory()->create([
            'round_id' => $round->id,
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'stake' => 100,
            'status' => BetStatus::Active,
        ]);

        app(CashoutService::class)->cashOut($bet->id, atMultiplier: 2.00);

        $this->assertSame('200.00', $wallet->fresh()->balance);
        $this->assertSame(0, $wallet->transactions()->where('type', WalletTransactionType::WithholdingTax)->count());
    }
}
