<?php

namespace Tests\Feature\Mpesa;

use App\Domain\Mpesa\B2cService;
use App\Domain\Mpesa\MpesaCallbackHandler;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class B2cResultTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDarajaB2c(string $conversationId): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'test-token']),
            '*/mpesa/b2c/v3/paymentrequest' => Http::response([
                'ConversationID' => $conversationId,
                'OriginatorConversationID' => 'origin-1',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Accept the service request successfully.',
            ]),
        ]);
    }

    public function test_initiate_debits_the_wallet_immediately_as_pending(): void
    {
        $this->fakeDarajaB2c('AG_20260911_conv1');

        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);

        $transaction = app(B2cService::class)->initiate($wallet, 200, $player->msisdn, 'WD-REF-1');

        $this->assertSame('800.00', $wallet->fresh()->balance);
        $this->assertSame(WalletTransactionStatus::Pending, $transaction->status);
        $this->assertSame('AG_20260911_conv1', $transaction->fresh()->metadata['conversation_id']);
    }

    public function test_a_successful_result_completes_the_withdrawal_without_touching_the_balance_again(): void
    {
        $this->fakeDarajaB2c('AG_20260911_conv2');

        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $transaction = app(B2cService::class)->initiate($wallet, 200, $player->msisdn, 'WD-REF-2');

        app(MpesaCallbackHandler::class)->handleB2cResult([
            'Result' => [
                'ResultType' => 0,
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'ConversationID' => 'AG_20260911_conv2',
            ],
        ]);

        $this->assertSame(WalletTransactionStatus::Completed, $transaction->fresh()->status);
        $this->assertSame('800.00', $wallet->fresh()->balance);
    }

    public function test_a_failed_result_reverses_the_debit_with_a_refund(): void
    {
        $this->fakeDarajaB2c('AG_20260911_conv3');

        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 1000]);
        $transaction = app(B2cService::class)->initiate($wallet, 200, $player->msisdn, 'WD-REF-3');

        app(MpesaCallbackHandler::class)->handleB2cResult([
            'Result' => [
                'ResultType' => 0,
                'ResultCode' => 1,
                'ResultDesc' => 'Insufficient funds in the utility account.',
                'ConversationID' => 'AG_20260911_conv3',
            ],
        ]);

        $this->assertSame(WalletTransactionStatus::Failed, $transaction->fresh()->status);
        $this->assertSame('1000.00', $wallet->fresh()->balance);

        $refund = $wallet->transactions()->where('type', WalletTransactionType::Refund)->sole();
        $this->assertSame('200.00', $refund->amount);
    }
}
