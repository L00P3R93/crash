<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMpesaB2cResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaB2cResultController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        ProcessMpesaB2cResult::dispatch($request->all())->onQueue('mpesa');

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
