<?php

namespace App\Listeners;

use App\Events\AviatorBettingOpened;
use App\Jobs\PlaceBotBet;
use App\Models\Player;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Local/dev only: makes a random subset of the seeded bot accounts
 * (database/seeders/BotPlayerSeeder) place a real bet every round, each
 * with a random `auto_cashout` target, so the live "active players" table
 * always has activity to show without needing real players.
 *
 * Each bot's bet is dispatched as a separate App\Jobs\PlaceBotBet with a
 * random delay spread across the betting window, rather than placed
 * synchronously here — placing all of them in the same instant this event
 * fires would make every bot appear in the live "active players" table in
 * one clump instead of trickling in the way real players actually would.
 *
 * No separate cash-out mechanism needed — every bet placed here is
 * indistinguishable from a real one to App\Listeners\ResolveAutoCashouts,
 * which already resolves any bet with a non-null auto_cashout the instant
 * the round crashes. A bot whose random target lands above the crash
 * multiplier simply never cashes out and is swept into `lost` by
 * RoundService::settleRound() like any other unresolved bet.
 *
 * Implements ShouldQueue rather than running synchronously: this event
 * fires inline inside the long-running `aviator:run-game-loop` worker,
 * which wraps each round in a single top-level try/catch — an uncaught
 * exception from a synchronous listener here would abort the current
 * round mid-flight, including any real bets already placed on it. Queuing
 * isolates any failure to a retryable job instead.
 */
class PlaceBotBets implements ShouldQueue
{
    /**
     * Leaves this many seconds free at the end of the betting window so a
     * delayed bot bet never lands after (or right on) betting closing.
     */
    private const CLOSING_BUFFER_SECONDS = 4;

    public function handle(AviatorBettingOpened $event): void
    {
        if (! config('aviator.bots_enabled')) {
            return;
        }

        try {
            $this->scheduleBotBets();
        } catch (Throwable $e) {
            Log::error('Scheduling bot bets failed.', ['exception' => $e->getMessage()]);
        }
    }

    private function scheduleBotBets(): void
    {
        $available = Player::query()->where('is_bot', true)->count();

        if ($available === 0) {
            return;
        }

        $minPlayers = (int) config('aviator.bots_min_players');
        $maxPlayers = (int) config('aviator.bots_max_players');
        $count = min($available, random_int($minPlayers, $maxPlayers));

        $minStake = (float) config('aviator.min_stake');
        $maxStake = (float) config('aviator.max_stake');
        $minCashout = (float) config('aviator.bots_min_auto_cashout');
        $maxCashout = (float) config('aviator.bots_max_auto_cashout');
        $maxDelay = max(1, (int) config('aviator.betting_window_seconds') - self::CLOSING_BUFFER_SECONDS);

        $bots = Player::query()->where('is_bot', true)->inRandomOrder()->limit($count)->get();

        foreach ($bots as $bot) {
            $stake = round(random_int((int) ($minStake * 100), (int) ($maxStake * 100)) / 100, 2);
            $autoCashout = round(random_int((int) ($minCashout * 100), (int) ($maxCashout * 100)) / 100, 2);

            PlaceBotBet::dispatch($bot->id, $stake, $autoCashout)
                ->delay(now()->addSeconds(random_int(0, $maxDelay)));
        }
    }
}
