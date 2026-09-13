<?php

namespace Tests\Feature\Ussd;

use App\Domain\Aviator\RoundService;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpAndFairnessTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    public function test_help_screen_documents_the_session_timeout_policy_and_links_to_fairness(): void
    {
        Player::factory()->withPin('1111')->create(['msisdn' => '254760000001']);

        $this->ussd('help-1', '254760000001', '');
        $response = $this->ussd('help-1', '254760000001', '4');

        $response->assertSee('HOW TO PLAY', false);
        $response->assertSee('it settles automatically against the', false);
        $response->assertSee('1: Verify Fairness', false);
    }

    public function test_fairness_screen_hides_the_seed_before_settlement_and_reveals_it_after(): void
    {
        $rounds = app(RoundService::class);
        $round = $rounds->createRound();

        Player::factory()->withPin('1111')->create(['msisdn' => '254760000002']);

        $this->ussd('fair-1', '254760000002', '');
        $this->ussd('fair-1', '254760000002', '4');
        $before = $this->ussd('fair-1', '254760000002', '4*1');

        $before->assertSee("Round {$round->round_number}", false);
        $before->assertSee($round->server_seed_hash, false);
        $before->assertSee('Reveal seed after round ends', false);
        $before->assertDontSee($round->server_seed, false);

        $rounds->openBetting($round);
        $rounds->startRound($round->fresh());
        $rounds->crashRound($round->fresh());
        $rounds->settleRound($round->fresh());

        $this->ussd('fair-2', '254760000002', '');
        $this->ussd('fair-2', '254760000002', '4');
        $after = $this->ussd('fair-2', '254760000002', '4*1');

        $after->assertSee("Server seed: {$round->server_seed}", false);
    }
}
