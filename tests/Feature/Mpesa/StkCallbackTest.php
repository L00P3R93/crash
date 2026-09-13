<?php

namespace Tests\Feature\Mpesa;

use App\Domain\Mpesa\MpesaCallbackHandler;
use App\Enums\TopupStatus;
use App\Models\Player;
use App\Models\Topup;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StkCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function successPayload(string $checkoutRequestId, int $amount = 250): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'merchant-1',
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => $amount],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                            ['Name' => 'TransactionDate', 'Value' => 20260911123000],
                            ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function failurePayload(string $checkoutRequestId): array
    {
        return [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'merchant-1',
                    'CheckoutRequestID' => $checkoutRequestId,
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user.',
                ],
            ],
        ];
    }

    public function test_successful_callback_credits_the_wallet_and_completes_the_topup(): void
    {
        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);
        $topup = Topup::factory()->create([
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'amount' => 250,
            'status' => TopupStatus::Pending,
            'provider_reference' => 'ws_CO_ABC',
        ]);

        app(MpesaCallbackHandler::class)->handleStkCallback($this->successPayload('ws_CO_ABC'));

        $topup->refresh();
        $this->assertSame(TopupStatus::Completed, $topup->status);
        $this->assertNotNull($topup->wallet_transaction_id);
        $this->assertSame('250.00', $wallet->fresh()->balance);
    }

    public function test_failed_callback_marks_the_topup_failed_without_crediting(): void
    {
        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);
        $topup = Topup::factory()->create([
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'amount' => 250,
            'status' => TopupStatus::Pending,
            'provider_reference' => 'ws_CO_FAIL',
        ]);

        app(MpesaCallbackHandler::class)->handleStkCallback($this->failurePayload('ws_CO_FAIL'));

        $this->assertSame(TopupStatus::Failed, $topup->fresh()->status);
        $this->assertSame('0.00', $wallet->fresh()->balance);
    }

    public function test_a_duplicate_callback_does_not_credit_twice(): void
    {
        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);
        Topup::factory()->create([
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'amount' => 250,
            'status' => TopupStatus::Pending,
            'provider_reference' => 'ws_CO_REPLAY',
        ]);

        $handler = app(MpesaCallbackHandler::class);
        $handler->handleStkCallback($this->successPayload('ws_CO_REPLAY'));
        $handler->handleStkCallback($this->successPayload('ws_CO_REPLAY'));

        $this->assertSame('250.00', $wallet->fresh()->balance);
    }

    public function test_a_callback_for_an_unknown_checkout_request_id_is_ignored(): void
    {
        $wallet = Wallet::factory()->create(['balance' => 0]);

        app(MpesaCallbackHandler::class)->handleStkCallback($this->successPayload('ws_CO_UNKNOWN'));

        $this->assertSame('0.00', $wallet->fresh()->balance);
    }
}
