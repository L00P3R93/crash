<?php

namespace Tests\Feature\Ussd;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WithdrawFlowTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    private function fakeDarajaB2c(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'test-token']),
            '*/mpesa/b2c/v3/paymentrequest' => Http::response([
                'ConversationID' => 'AG_test_conv',
                'ResponseCode' => '0',
            ]),
        ]);
    }

    public function test_withdrawal_requires_the_correct_pin_before_initiating_b2c(): void
    {
        $this->fakeDarajaB2c();

        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254750000001']);
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $this->ussd('wd-1', '254750000001', '');
        $this->ussd('wd-1', '254750000001', '3'); // Account
        $this->ussd('wd-1', '254750000001', '3*2'); // Withdraw
        $this->ussd('wd-1', '254750000001', '3*2*200'); // amount
        $confirm = $this->ussd('wd-1', '254750000001', '3*2*200*1'); // confirm

        $confirm->assertSee('Enter your PIN:', false);
        // Nothing is debited yet — only after PIN verification succeeds.
        $this->assertSame('1000.00', $wallet->fresh()->balance);

        $wrong = $this->ussd('wd-1', '254750000001', '3*2*200*1*0000');
        $wrong->assertSee('Incorrect PIN. Try again:', false);
        $this->assertSame('1000.00', $wallet->fresh()->balance);

        $right = $this->ussd('wd-1', '254750000001', '3*2*200*1*0000*1111');
        $right->assertSee('Withdrawal requested. KSh 200.00 will be sent', false);

        // The debit is written immediately as pending once PIN-verified —
        // the balance reflects it right away, before Daraja's async result.
        $transaction = $wallet->transactions()->where('type', WalletTransactionType::Withdrawal)->sole();
        $this->assertSame(WalletTransactionStatus::Pending, $transaction->status);
        $this->assertSame('800.00', $wallet->fresh()->balance);
    }

    public function test_bot_accounts_cannot_withdraw(): void
    {
        $this->fakeDarajaB2c();

        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254750000003', 'is_bot' => true]);
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $this->ussd('wd-3', '254750000003', '');
        $this->ussd('wd-3', '254750000003', '3');
        $this->ussd('wd-3', '254750000003', '3*2');
        $this->ussd('wd-3', '254750000003', '3*2*200');
        $this->ussd('wd-3', '254750000003', '3*2*200*1');
        $this->ussd('wd-3', '254750000003', '3*2*200*1*0000');

        $response = $this->ussd('wd-3', '254750000003', '3*2*200*1*0000*1111');

        $response->assertSee('Withdrawals are not available for this account.', false);
        $this->assertSame('1000.00', $wallet->fresh()->balance);
    }

    public function test_withdrawing_more_than_the_balance_is_rejected(): void
    {
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254750000002']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 50]);

        $this->ussd('wd-2', '254750000002', '');
        $this->ussd('wd-2', '254750000002', '3');
        $this->ussd('wd-2', '254750000002', '3*2');
        $response = $this->ussd('wd-2', '254750000002', '3*2*200');

        $response->assertSee('Insufficient balance.', false);
    }
}
