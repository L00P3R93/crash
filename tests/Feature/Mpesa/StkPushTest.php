<?php

namespace Tests\Feature\Mpesa;

use App\Domain\Mpesa\StkPushService;
use App\Models\Player;
use App\Models\Topup;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StkPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_initiate_stores_the_checkout_request_id_from_daraja(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'test-token', 'expires_in' => '3599']),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'MerchantRequestID' => 'merchant-1',
                'CheckoutRequestID' => 'ws_CO_123456789',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ]),
        ]);

        $player = Player::factory()->create();
        $wallet = Wallet::factory()->create(['player_id' => $player->id]);
        $topup = Topup::factory()->create([
            'player_id' => $player->id,
            'wallet_id' => $wallet->id,
            'amount' => 100,
        ]);

        $response = app(StkPushService::class)->initiate($topup, $player->msisdn);

        $this->assertSame('ws_CO_123456789', $response['CheckoutRequestID']);
        $this->assertSame('ws_CO_123456789', $topup->fresh()->provider_reference);

        Http::assertSent(function ($request) use ($player) {
            return $request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
                && $request['PhoneNumber'] === $player->msisdn
                && $request['Amount'] === 100;
        });
    }
}
