<?php

namespace App\Jobs;

use App\Domain\Aviator\BetService;
use App\Models\Player;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Places a single bot's bet. Dispatched with a random delay by
 * App\Listeners\PlaceBotBets so bots trickle into the round across the
 * betting window instead of all appearing in the live "active players"
 * table in the same instant.
 */
class PlaceBotBet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $playerId,
        public readonly float $stake,
        public readonly float $autoCashout,
    ) {}

    public function handle(BetService $bets): void
    {
        $player = Player::find($this->playerId);

        if (! $player) {
            return;
        }

        try {
            $bets->placeBet($player, $this->stake, 'bot', (string) Str::uuid(), $this->autoCashout);
        } catch (Throwable $e) {
            // Most likely the round already closed betting before this
            // delayed job ran (worker lag) — not worth failing loudly for.
            Log::warning('Bot bet failed.', ['player_id' => $this->playerId, 'exception' => $e->getMessage()]);
        }
    }
}
