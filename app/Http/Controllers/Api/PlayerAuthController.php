<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\Player;
use App\Support\MsisdnNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Web login mirrors the USSD identity: msisdn + the same 4-digit PIN set via
 * SetPinScreen (architecture doc §28's phone-based identity, no separate
 * web password). A player who has never set a PIN over USSD simply can't
 * log in to the web yet — there is no self-service PIN creation here.
 */
class PlayerAuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        // A player's own msisdn is always stored normalized (see
        // MsisdnNormalizer) — match web login input the same way, so
        // "+254712345678" or "0712345678" logs in the same account
        // "254712345678" was registered under via USSD.
        $player = Player::query()->where('msisdn', MsisdnNormalizer::normalize($data['msisdn']))->first();

        if (! $player || ! $player->pin_hash || ! Hash::check($data['pin'], $player->pin_hash)) {
            throw ValidationException::withMessages([
                'msisdn' => ['Incorrect phone number or PIN.'],
            ]);
        }

        $token = $player->createToken($data['device_name'] ?? 'web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'player' => [
                'id' => $player->id,
                'msisdn' => $player->msisdn,
                'name' => $player->name,
                'balance' => (float) $player->wallet->balance,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var Player $player */
        $player = $request->user();

        $player->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Player $player */
        $player = $request->user();

        return response()->json([
            'id' => $player->id,
            'msisdn' => $player->msisdn,
            'name' => $player->name,
            'balance' => (float) $player->wallet->balance,
        ]);
    }
}
