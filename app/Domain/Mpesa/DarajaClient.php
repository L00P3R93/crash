<?php

namespace App\Domain\Mpesa;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin authenticated HTTP client for Safaricom's Daraja API. A dedicated SDK
 * isn't required — this plus Laravel's Http client (already Guzzle-backed)
 * is enough and keeps us off unmaintained third-party packages.
 */
class DarajaClient
{
    public function baseUrl(): string
    {
        return config('mpesa.env') === 'production'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * OAuth token, cached for ~55 minutes (Daraja tokens are valid for 60).
     */
    public function accessToken(): string
    {
        return Cache::remember('mpesa:oauth_token', now()->addMinutes(55), function () {
            $response = Http::withBasicAuth(
                (string) config('mpesa.consumer_key'),
                (string) config('mpesa.consumer_secret'),
            )
                ->get("{$this->baseUrl()}/oauth/v1/generate", ['grant_type' => 'client_credentials'])
                ->throw();

            return (string) $response->json('access_token');
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload): array
    {
        return Http::withToken($this->accessToken())
            ->post("{$this->baseUrl()}{$path}", $payload)
            ->throw()
            ->json();
    }
}
