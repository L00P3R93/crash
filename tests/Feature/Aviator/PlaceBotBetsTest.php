<?php

namespace Tests\Feature\Aviator;

use App\Domain\Aviator\RoundService;
use App\Enums\BetStatus;
use App\Jobs\PlaceBotBet;
use App\Models\AviatorBet;
use App\Models\Player;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlaceBotBetsTest extends TestCase
{
    use RefreshDatabase;

    private function seedBots(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $bot = Player::factory()->create(['is_bot' => true]);
            Wallet::factory()->create(['player_id' => $bot->id, 'balance' => 1_000_000]);
        }
    }

    public function test_does_nothing_when_bots_are_disabled(): void
    {
        config(['aviator.bots_enabled' => false]);
        $this->seedBots(25);

        $round = app(RoundService::class)->createRound();
        $round = app(RoundService::class)->openBetting($round);

        $this->assertSame(0, AviatorBet::query()->where('round_id', $round->id)->count());
    }

    public function test_does_nothing_when_no_bots_are_seeded(): void
    {
        config(['aviator.bots_enabled' => true]);

        $round = app(RoundService::class)->createRound();
        $round = app(RoundService::class)->openBetting($round);

        $this->assertSame(0, AviatorBet::query()->where('round_id', $round->id)->count());
    }

    public function test_places_bets_for_a_random_subset_of_bots_within_the_configured_range(): void
    {
        config([
            'aviator.bots_enabled' => true,
            'aviator.bots_min_players' => 5,
            'aviator.bots_max_players' => 10,
        ]);
        $this->seedBots(25);

        $round = app(RoundService::class)->createRound();
        $round = app(RoundService::class)->openBetting($round);

        $count = AviatorBet::query()->where('round_id', $round->id)->where('channel', 'bot')->count();

        $this->assertGreaterThanOrEqual(5, $count);
        $this->assertLessThanOrEqual(10, $count);
    }

    public function test_each_bot_bet_has_an_auto_cashout_target_within_bounds(): void
    {
        config([
            'aviator.bots_enabled' => true,
            'aviator.bots_min_players' => 5,
            'aviator.bots_max_players' => 5,
            'aviator.bots_min_auto_cashout' => 1.10,
            'aviator.bots_max_auto_cashout' => 10.00,
        ]);
        $this->seedBots(5);

        $round = app(RoundService::class)->createRound();
        $round = app(RoundService::class)->openBetting($round);

        $bets = AviatorBet::query()->where('round_id', $round->id)->where('channel', 'bot')->get();

        $this->assertCount(5, $bets);

        foreach ($bets as $bet) {
            $this->assertSame(BetStatus::Active, $bet->status);
            $this->assertNotNull($bet->auto_cashout);
            $this->assertGreaterThanOrEqual(1.10, (float) $bet->auto_cashout);
            $this->assertLessThanOrEqual(10.00, (float) $bet->auto_cashout);
        }
    }

    public function test_a_failure_placing_one_bots_bet_does_not_abort_the_others(): void
    {
        config([
            'aviator.bots_enabled' => true,
            'aviator.bots_min_players' => 3,
            'aviator.bots_max_players' => 3,
        ]);
        $this->seedBots(2);

        // No wallet for this one — BetService throws for it, the listener
        // must still place bets for the other two instead of aborting.
        Player::factory()->create(['is_bot' => true]);

        $round = app(RoundService::class)->createRound();
        $round = app(RoundService::class)->openBetting($round);

        $this->assertSame(2, AviatorBet::query()->where('round_id', $round->id)->count());
    }

    public function test_bot_bets_are_spread_across_the_betting_window_instead_of_dispatched_at_once(): void
    {
        // Only fake the job being asserted on — PlaceBotBets itself is a
        // queued listener, and faking the queue wholesale would swallow its
        // own dispatch (as a CallQueuedListener) before it ever runs.
        Queue::fake([PlaceBotBet::class]);

        config([
            'aviator.bots_enabled' => true,
            'aviator.bots_min_players' => 10,
            'aviator.bots_max_players' => 10,
            'aviator.betting_window_seconds' => 25,
        ]);
        $this->seedBots(10);

        $round = app(RoundService::class)->createRound();
        app(RoundService::class)->openBetting($round);

        Queue::assertPushed(PlaceBotBet::class, 10);

        $delayTimestamps = collect(Queue::pushedJobs()[PlaceBotBet::class] ?? [])
            ->pluck('job')
            ->map(fn (PlaceBotBet $job) => $job->delay?->getTimestamp());

        // Not a strict distribution check — just that these weren't all
        // dispatched for the same instant, which is the bug being fixed.
        $this->assertGreaterThan(1, $delayTimestamps->unique()->count());
    }
}
