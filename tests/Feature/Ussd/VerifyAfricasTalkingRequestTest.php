<?php

namespace Tests\Feature\Ussd;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyAfricasTalkingRequestTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    public function test_a_request_missing_the_configured_secret_is_rejected(): void
    {
        config(['africastalking.shared_secret' => 'topsecret']);

        $response = $this->ussd('secret-1', '254770000001', '');

        $response->assertForbidden();
    }

    public function test_a_request_with_the_correct_secret_is_accepted(): void
    {
        config(['africastalking.shared_secret' => 'topsecret']);

        $response = $this->post('/ussd/aviator?secret=topsecret', [
            'sessionId' => 'secret-2',
            'phoneNumber' => '254770000002',
            'serviceCode' => '*384*1234#',
            'text' => '',
        ]);

        $response->assertOk();
    }

    public function test_an_empty_secret_allows_any_request(): void
    {
        config(['africastalking.shared_secret' => '']);

        $response = $this->ussd('secret-3', '254770000003', '');

        $response->assertOk();
    }
}
