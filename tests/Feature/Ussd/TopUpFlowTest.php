<?php

namespace Tests\Feature\Ussd;

use App\Enums\TopupStatus;
use App\Models\Player;
use App\Models\Topup;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TopUpFlowTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    public function test_a_valid_amount_asks_for_confirmation_then_initiates_stk_push(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'test-token']),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'CheckoutRequestID' => 'ws_CO_TOPUP1',
                'ResponseCode' => '0',
            ]),
        ]);

        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254740000001']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);

        $this->ussd('topup-1', '254740000001', '');
        $this->ussd('topup-1', '254740000001', '2');
        $confirmResponse = $this->ussd('topup-1', '254740000001', '2*200');

        $confirmResponse->assertSee('Confirm top up KSh 200.00 via M-PESA?', false);

        $finalResponse = $this->ussd('topup-1', '254740000001', '2*200*1');

        $finalResponse->assertSee('END STK push sent', false);

        $topup = Topup::query()->where('player_id', $player->id)->sole();
        $this->assertSame(TopupStatus::Pending, $topup->status);
        $this->assertSame('ws_CO_TOPUP1', $topup->provider_reference);
        // The balance has NOT moved yet — only the async M-Pesa callback does that.
        $this->assertSame('0.00', $player->wallet->fresh()->balance);
    }

    public function test_cancelling_the_confirmation_returns_to_the_main_menu_without_a_topup(): void
    {
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254740000002']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);

        $this->ussd('topup-2', '254740000002', '');
        $this->ussd('topup-2', '254740000002', '2');
        $this->ussd('topup-2', '254740000002', '2*200');
        $response = $this->ussd('topup-2', '254740000002', '2*200*2');

        $response->assertSee('Shinda Na Aviator', false);
        $this->assertSame(0, Topup::query()->where('player_id', $player->id)->count());
    }

    public function test_an_amount_outside_the_configured_range_is_rejected(): void
    {
        $player = Player::factory()->withPin('1111')->create(['msisdn' => '254740000003']);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => 0]);

        $this->ussd('topup-3', '254740000003', '');
        $this->ussd('topup-3', '254740000003', '2');
        $response = $this->ussd('topup-3', '254740000003', '2*20000');

        $response->assertSee('Amount must be between KSh 50.00 and KSh 10,000.00.', false);
    }
}
