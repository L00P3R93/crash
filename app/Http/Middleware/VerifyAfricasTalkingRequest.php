<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAfricasTalkingRequest
{
    /**
     * Checks a shared secret passed as a `?secret=` query param on the
     * webhook URL registered with Africa's Talking. An empty secret
     * (sandbox/local convenience) allows everything — production must set
     * AT_SHARED_SECRET.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('africastalking.shared_secret');

        if ($secret !== null && $secret !== '' && $request->query('secret') !== $secret) {
            abort(403, "Invalid or missing Africa's Talking shared secret.");
        }

        return $next($request);
    }
}
