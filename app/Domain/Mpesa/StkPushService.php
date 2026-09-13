<?php

namespace App\Domain\Mpesa;

use App\Models\Topup;

class StkPushService
{
    public function __construct(
        private readonly DarajaClient $client = new DarajaClient
    ) {}

    /**
     * Initiates Lipa Na M-Pesa Online (STK push) for a pending top-up and
     * stores Daraja's CheckoutRequestID so the callback can find it again.
     *
     * @return array<string, mixed>
     */
    public function initiate(Topup $topup, string $msisdn): array
    {
        $timestamp = now()->format('YmdHis');
        $shortcode = (string) config('mpesa.shortcode');
        $password = base64_encode($shortcode.config('mpesa.passkey').$timestamp);

        $response = $this->client->post('/mpesa/stkpush/v1/processrequest', [
            'BusinessShortCode' => $shortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => (int) round((float) $topup->amount),
            'PartyA' => $msisdn,
            'PartyB' => $shortcode,
            'PhoneNumber' => $msisdn,
            'CallBackURL' => config('mpesa.stk_callback_url'),
            'AccountReference' => "AVIATOR-TOPUP-{$topup->id}",
            'TransactionDesc' => 'Aviator wallet top-up',
        ]);

        if (isset($response['CheckoutRequestID'])) {
            $topup->forceFill(['provider_reference' => $response['CheckoutRequestID']])->save();
        }

        return $response;
    }
}
