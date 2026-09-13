<?php

namespace App\Domain\Ussd;

use App\Enums\PlayerStatus;
use App\Enums\UssdSessionStatus;
use App\Models\Player;
use App\Models\UssdSession;
use App\Models\Wallet;
use Illuminate\Support\Carbon;

class UssdSessionManager
{
    /**
     * The phone number itself is the account — a player is created silently
     * on first contact, no explicit "register" step. A never-set PIN routes
     * straight into SetPinScreen before the main menu.
     */
    public function loadOrCreate(string $sessionId, string $msisdn, string $serviceCode, ?string $networkCode): UssdSession
    {
        $existing = UssdSession::query()->where('session_id', $sessionId)->first();

        if ($existing) {
            return $existing;
        }

        $player = Player::query()->firstOrCreate(
            ['msisdn' => $msisdn],
            ['status' => PlayerStatus::Active, 'registered_via' => 'ussd', 'last_seen_at' => now()],
        );

        Wallet::query()->firstOrCreate(['player_id' => $player->id], ['balance' => 0, 'currency' => 'KES']);

        $player->forceFill(['last_seen_at' => now()])->save();

        return UssdSession::query()->create([
            'session_id' => $sessionId,
            'msisdn' => $msisdn,
            'service_code' => $serviceCode,
            'network_code' => $networkCode,
            'player_id' => $player->id,
            'current_screen' => $player->pin_set_at === null ? 'set_pin' : 'main_menu',
            'state' => [],
            'status' => UssdSessionStatus::Active,
            'started_at' => now(),
            'last_interaction_at' => now(),
        ]);
    }

    /**
     * Africa's Talking sends the full accumulated `text` param each request
     * (e.g. "1*50" on the third leg) — we only care about what was just
     * typed in answer to the current screen's prompt.
     */
    public function extractLastInput(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $parts = explode('*', $text);

        return (string) end($parts);
    }

    public function persist(UssdSession $session, string $lastInput): void
    {
        $session->last_input = $lastInput;
        $session->last_interaction_at = Carbon::now();
        $session->save();
    }

    public function endSession(UssdSession $session, string $lastInput): void
    {
        $session->last_input = $lastInput;
        $session->last_interaction_at = Carbon::now();
        $session->status = UssdSessionStatus::Completed;
        $session->ended_at = Carbon::now();
        $session->save();
    }
}
