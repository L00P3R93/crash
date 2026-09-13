<?php

namespace Tests\Feature\Console;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormalizeMsisdnsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_but_does_not_change_a_stale_msisdn(): void
    {
        $player = Player::factory()->create(['msisdn' => '+254712345678']);

        $this->artisan('players:normalize-msisdns')
            ->expectsOutputToContain('#'.$player->id.': +254712345678 -> 254712345678')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('+254712345678', $player->fresh()->msisdn);
    }

    public function test_apply_rewrites_a_stale_msisdn(): void
    {
        $player = Player::factory()->create(['msisdn' => '+254712345678']);

        $this->artisan('players:normalize-msisdns', ['--apply' => true])
            ->assertSuccessful();

        $this->assertSame('254712345678', $player->fresh()->msisdn);
    }

    public function test_a_normalization_collision_is_reported_and_left_untouched(): void
    {
        $withPlus = Player::factory()->create(['msisdn' => '+254712345678']);
        $canonical = Player::factory()->create(['msisdn' => '254712345678']);

        $this->artisan('players:normalize-msisdns', ['--apply' => true])
            ->expectsOutputToContain('254712345678 <- player ids '.$withPlus->id.', '.$canonical->id)
            ->expectsOutputToContain('manual review')
            ->assertSuccessful();

        $this->assertSame('+254712345678', $withPlus->fresh()->msisdn);
        $this->assertSame('254712345678', $canonical->fresh()->msisdn);
    }

    public function test_nothing_to_do_when_every_msisdn_is_already_canonical(): void
    {
        Player::factory()->create(['msisdn' => '254712345678']);

        $this->artisan('players:normalize-msisdns')
            ->expectsOutputToContain('No safe renames needed')
            ->assertSuccessful();
    }
}
