<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web top-up/cash-out — deliberately stubbed. `App\Domain\Mpesa\StkPushService`
 * and `B2cService` exist and work, but today they're only ever invoked from
 * USSD screens (`App\Domain\Ussd\Screens\TopUpScreen`, `WithdrawScreen`).
 * Wiring these to a web-triggered STK push / withdrawal is a separate,
 * payment-adjacent follow-up task — this only gives the frontend a real
 * endpoint shape to build the wallet modal against in the meantime.
 */
class WalletController extends Controller
{
    public function topup(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Web top-up is not available yet — please use the USSD menu to load your wallet.',
        ], 501);
    }

    public function withdraw(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Web cash-out to M-Pesa is not available yet — please use the USSD menu to withdraw.',
        ], 501);
    }
}
