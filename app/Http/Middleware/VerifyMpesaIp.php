<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyMpesaIp
{
    /**
     * Rejects webhook requests that don't originate from an allowlisted
     * Safaricom IP. An empty allowlist (sandbox/local convenience) allows
     * everything — production deployments must set MPESA_ALLOWED_IPS.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('mpesa.allowed_ips', []);

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            abort(403, 'Unrecognized source IP.');
        }

        return $next($request);
    }
}
