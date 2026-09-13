<?php

namespace Tests\Feature\Mpesa;

use App\Jobs\ProcessMpesaB2cResult;
use App\Jobs\ProcessMpesaStkCallback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_stk_callback_route_acknowledges_immediately_and_queues_processing(): void
    {
        Queue::fake();

        $response = $this->postJson('/webhooks/mpesa/stk-callback', [
            'Body' => ['stkCallback' => ['CheckoutRequestID' => 'x']],
        ]);

        $response->assertOk()->assertJson(['ResultCode' => 0]);

        Queue::assertPushedOn('mpesa', ProcessMpesaStkCallback::class);
    }

    public function test_b2c_result_route_acknowledges_immediately_and_queues_processing(): void
    {
        Queue::fake();

        $response = $this->postJson('/webhooks/mpesa/b2c-result', [
            'Result' => ['ConversationID' => 'x'],
        ]);

        $response->assertOk()->assertJson(['ResultCode' => 0]);

        Queue::assertPushedOn('mpesa', ProcessMpesaB2cResult::class);
    }

    public function test_requests_from_a_non_allowlisted_ip_are_rejected(): void
    {
        config(['mpesa.allowed_ips' => ['203.0.113.5']]);

        $response = $this->postJson('/webhooks/mpesa/stk-callback', []);

        $response->assertForbidden();
    }

    public function test_an_empty_allowlist_permits_any_source_ip(): void
    {
        config(['mpesa.allowed_ips' => []]);
        Queue::fake();

        $response = $this->postJson('/webhooks/mpesa/stk-callback', []);

        $response->assertOk();
    }
}
