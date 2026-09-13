<?php

namespace Tests\Feature\Ussd;

use Illuminate\Testing\TestResponse;

trait InteractsWithUssd
{
    private function ussd(string $sessionId, string $phone, string $text): TestResponse
    {
        return $this->post('/ussd/aviator', [
            'sessionId' => $sessionId,
            'phoneNumber' => $phone,
            'serviceCode' => '*384*1234#',
            'networkCode' => '63902',
            'text' => $text,
        ]);
    }
}
