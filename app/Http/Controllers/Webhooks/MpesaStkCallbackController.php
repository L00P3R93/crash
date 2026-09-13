<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMpesaStkCallback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaStkCallbackController extends Controller
{
    /**
     * Responds to Safaricom immediately and processes the actual ledger
     * crediting on the queue — Daraja expects a fast acknowledgement
     * regardless of how long crediting takes (project-structure doc §5).
     */
    public function handle(Request $request): JsonResponse
    {
        ProcessMpesaStkCallback::dispatch($request->all())->onQueue('mpesa');

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
