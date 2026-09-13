<?php

namespace Tests\Feature\Aviator;

use App\Domain\Aviator\ProvablyFairService;
use App\Domain\Aviator\RoundScheduler;
use App\Domain\Aviator\RoundService;
use App\Enums\RoundStatus;
use App\Events\AviatorRoundCrashed;
use App\Events\AviatorRoundStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RoundLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_round_generates_and_locks_in_the_crash_point(): void
    {
        $round = app(RoundService::class)->createRound();

        $this->assertSame(RoundStatus::Scheduled, $round->status);
        $this->assertNotNull($round->crash_multiplier);
        $this->assertNotNull($round->server_seed);
        $this->assertNotNull($round->server_seed_hash);

        $lockedInCrashMultiplier = $round->crash_multiplier;

        $round->refresh();

        $this->assertSame((string) $lockedInCrashMultiplier, (string) $round->crash_multiplier);
    }

    public function test_server_seed_hash_is_reproducible_from_the_revealed_seed(): void
    {
        $round = app(RoundService::class)->createRound();
        $provablyFair = app(ProvablyFairService::class);

        $verified = $provablyFair->verify(
            serverSeed: $round->server_seed,
            expectedServerSeedHash: $round->server_seed_hash,
            clientSeed: $round->client_seed,
            nonce: $round->nonce,
            houseEdge: (float) $round->house_edge,
            expectedCrashMultiplier: (float) $round->crash_multiplier,
        );

        $this->assertTrue($verified);
    }

    public function test_scheduler_drives_a_round_through_its_full_lifecycle(): void
    {
        Event::fake([AviatorRoundStarted::class, AviatorRoundCrashed::class]);

        // Keep the test fast: no betting window to wait out, a steep
        // acceleration constant collapses the crash delay to ~1 second, and
        // no post-round pause either.
        config([
            'aviator.betting_window_seconds' => 0,
            'aviator.acceleration_k' => 50,
            'aviator.post_round_pause_seconds' => 0,
        ]);

        $round = app(RoundScheduler::class)->runOnce();

        $this->assertSame(RoundStatus::Settled, $round->status);
        $this->assertNotNull($round->started_at);
        $this->assertNotNull($round->crashed_at);
        $this->assertNotNull($round->settled_at);

        Event::assertDispatched(AviatorRoundStarted::class);
        Event::assertDispatched(AviatorRoundCrashed::class);
    }

    public function test_round_numbers_increment_sequentially(): void
    {
        $service = app(RoundService::class);

        $first = $service->createRound();
        $second = $service->createRound();

        $this->assertSame($first->round_number + 1, $second->round_number);
    }
}
