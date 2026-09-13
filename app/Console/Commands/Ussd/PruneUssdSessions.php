<?php

namespace App\Console\Commands\Ussd;

use App\Enums\UssdSessionStatus;
use App\Models\UssdSession;
use Illuminate\Console\Command;

class PruneUssdSessions extends Command
{
    protected $signature = 'ussd:prune-sessions';

    protected $description = "Expires stale 'active' USSD sessions past Africa's Talking's session window. Any bet mid-ladder on an abandoned session still resolves normally when its round settles — this only cleans up session bookkeeping.";

    public function handle(): int
    {
        $ttl = (int) config('africastalking.session_ttl_seconds');

        $expired = UssdSession::query()
            ->where('status', UssdSessionStatus::Active)
            ->where('last_interaction_at', '<', now()->subSeconds($ttl))
            ->update(['status' => UssdSessionStatus::Expired, 'ended_at' => now()]);

        if ($expired > 0) {
            $this->info("Expired {$expired} stale USSD session(s).");
        }

        return self::SUCCESS;
    }
}
