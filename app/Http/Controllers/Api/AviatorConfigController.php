<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public paytable info the web bet form needs up front — the same numbers
 * the USSD bet-entry screen prints (architecture doc §28.3's "97% RTP | Up
 * to 5,000x stake | Enter bet KSh 10-2,000").
 */
class AviatorConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'min_stake' => (float) config('aviator.min_stake'),
            'max_stake' => (float) config('aviator.max_stake'),
            'max_multiplier' => (float) config('aviator.max_multiplier'),
            'rtp' => 1 - (float) config('aviator.house_edge'),
        ]);
    }
}
