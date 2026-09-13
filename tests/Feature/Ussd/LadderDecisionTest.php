<?php

namespace Tests\Feature\Ussd;

use App\Domain\Aviator\RoundService;
use App\Enums\BetStatus;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Models\Player;
use App\Models\UssdSession;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LadderDecisionTest extends TestCase
{
    use InteractsWithUssd, RefreshDatabase;

    private function openRound(float $crashMultiplier): AviatorRound
    {
        $rounds = app(RoundService::class);
        $round = $rounds->createRound();
        $round->forceFill(['crash_multiplier' => $crashMultiplier])->save();
        $rounds->openBetting($round);

        return $round->fresh();
    }

    private function makePlayer(string $msisdn, float $balance = 1000): Player
    {
        $player = Player::factory()->withPin('1111')->create(['msisdn' => $msisdn]);
        Wallet::factory()->create(['player_id' => $player->id, 'balance' => $balance]);

        return $player;
    }

    /**
     * Places a bet (round still `betting`), then transitions the round to
     * `running` — exactly as it would be for real by the time a USSD player
     * reaches any ladder decision, since RoundScheduler advances the round
     * in the background on its own clock, independent of how fast the
     * player answers menu prompts.
     */
    private function placeBetAndStartRound(AviatorRound $round, string $sessionId, string $phone, string $stake): string
    {
        $this->ussd($sessionId, $phone, '');
        $this->ussd($sessionId, $phone, '1');
        $this->ussd($sessionId, $phone, "1*{$stake}");

        app(RoundService::class)->startRound($round->fresh());

        return "1*{$stake}";
    }

    /**
     * Continues an already-accumulated `text` with further ladder choices,
     * one real HTTP request per step, returning the final response.
     *
     * @param  array<int, string>  $steps
     */
    private function continueSteps(string $sessionId, string $phone, string $accumulated, array $steps): TestResponse
    {
        $response = null;

        foreach ($steps as $step) {
            $accumulated .= "*{$step}";
            $response = $this->ussd($sessionId, $phone, $accumulated);
        }

        return $response;
    }

    public function test_the_first_checkpoint_offers_the_documented_probabilities(): void
    {
        $round = $this->openRound(4.73);
        $this->makePlayer('254720000001');

        $this->placeBetAndStartRound($round, 'ladder-1', '254720000001', '100');

        $response = $this->ussd('ladder-1', '254720000001', '1*100');

        // Matches architecture doc §27.1: from 1.00x, Continue to 1.10x | 88%,
        // Rocket to 2.00x | 48% (truncated, not rounded, per §27.3).
        $response->assertSee('2: Continue to 1.10x | 88 pct chance', false);
        $response->assertSee('3: Rocket to 2.00x | 48 pct chance', false);
    }

    public function test_surviving_a_rung_advances_the_ladder_with_conditional_probabilities(): void
    {
        $round = $this->openRound(4.73);
        $player = $this->makePlayer('254720000002');

        $accumulated = $this->placeBetAndStartRound($round, 'ladder-2', '254720000002', '100');

        // Rocket to 2.00x (survives, since crash is 4.73).
        $response = $this->continueSteps('ladder-2', '254720000002', $accumulated, ['3']);

        $response->assertSee('Rocket reached 2.00x', false);
        $response->assertSee('1: Cash Out', false);
        $response->assertSee('3: Rocket to 4.00x | 50 pct chance', false);

        $bet = AviatorBet::query()->where('player_id', $player->id)->sole();
        $this->assertSame(BetStatus::Active, $bet->status);
        $this->assertSame(1, $bet->ladderSteps()->count());
    }

    public function test_cashing_out_mid_ladder_credits_the_net_payout(): void
    {
        config(['aviator.winnings_tax_rate' => 0.0]);
        $round = $this->openRound(4.73);
        $player = $this->makePlayer('254720000003');

        $accumulated = $this->placeBetAndStartRound($round, 'ladder-3', '254720000003', '100');

        // Rocket to 2.00 -> Rocket to 4.00 -> Continue to 4.40 -> Cash Out.
        $response = $this->continueSteps('ladder-3', '254720000003', $accumulated, ['3', '3', '2', '1']);

        $response->assertSee('WON', false);
        $response->assertSee('Cash Out 4.40x | Crash 4.73x', false);
        $response->assertSee('Paid KSh 440.00 | Net +KSh 340.00', false);

        $bet = AviatorBet::query()->where('player_id', $player->id)->sole();
        $this->assertSame(BetStatus::Won, $bet->status);
        $this->assertSame('440.00', $bet->payout);
        $this->assertSame('1340.00', $player->wallet->fresh()->balance);
    }

    public function test_choosing_a_rung_the_round_cannot_reach_shows_crashed_and_marks_the_bet_lost(): void
    {
        $round = $this->openRound(4.73);
        $player = $this->makePlayer('254720000004');

        $accumulated = $this->placeBetAndStartRound($round, 'ladder-4', '254720000004', '100');

        // Rocket to 2.00 -> Rocket to 4.00 -> Rocket to 8.00 (crashes, since crash is 4.73).
        $response = $this->continueSteps('ladder-4', '254720000004', $accumulated, ['3', '3', '3']);

        $response->assertSee('CRASHED at 4.73x', false);
        $response->assertSee('Lost KSh 100.00', false);

        $bet = AviatorBet::query()->where('player_id', $player->id)->sole();
        $this->assertSame(BetStatus::Lost, $bet->status);
        $this->assertSame('0.00', $bet->payout);
        $this->assertSame('900.00', $player->wallet->fresh()->balance);
    }

    public function test_play_again_after_a_crash_places_a_new_bet_with_the_same_stake(): void
    {
        $round = $this->openRound(4.73);
        $player = $this->makePlayer('254720000005');

        $accumulated = $this->placeBetAndStartRound($round, 'ladder-5', '254720000005', '100');

        // Crash via a hopeless rocket chain (one request per rung, exactly
        // as Africa's Talking would send them).
        $this->continueSteps('ladder-5', '254720000005', $accumulated, ['3', '3', '3']);
        $accumulated .= '*3*3*3';

        // A fresh round must be open in `betting` for "Play Again" to work,
        // exactly as it would for real once the next round comes around.
        $this->openRound(4.73);
        $response = $this->continueSteps('ladder-5', '254720000005', $accumulated, ['1']);

        $response->assertSee('Stake KSh 100.00 | Now 1.00x', false);

        $this->assertSame(2, AviatorBet::query()->where('player_id', $player->id)->count());
        $this->assertSame('800.00', $player->wallet->fresh()->balance);
    }

    public function test_reaching_the_configured_max_multiplier_offers_only_cash_out(): void
    {
        config(['aviator.max_multiplier' => 5000]);
        $round = $this->openRound(5000.00);
        $this->makePlayer('254720000006');

        $this->placeBetAndStartRound($round, 'ladder-6', '254720000006', '100');

        $session = UssdSession::query()->where('session_id', 'ladder-6')->sole();
        $session->state = array_merge($session->state, ['current_rung' => 5000.00]);
        $session->save();

        $response = $this->ussd('ladder-6', '254720000006', '1*100*2');

        $response->assertSee('Max reached!', false);
        $response->assertSee('1: Cash Out', false);
        $response->assertDontSee('3: Rocket', false);
    }

    public function test_an_invalid_ladder_choice_is_rejected(): void
    {
        $round = $this->openRound(4.73);
        $this->makePlayer('254720000007');

        $accumulated = $this->placeBetAndStartRound($round, 'ladder-7', '254720000007', '100');

        $response = $this->continueSteps('ladder-7', '254720000007', $accumulated, ['9']);

        $response->assertSee('Invalid choice. Please try again.', false);
    }

    public function test_menu_during_an_active_bet_returns_to_the_main_menu_without_resolving_it(): void
    {
        $round = $this->openRound(4.73);
        $player = $this->makePlayer('254720000008');

        $accumulated = $this->placeBetAndStartRound($round, 'ladder-8', '254720000008', '100');

        $response = $this->continueSteps('ladder-8', '254720000008', $accumulated, ['0']);

        $response->assertSee('Shinda Na Aviator', false);

        $bet = AviatorBet::query()->where('player_id', $player->id)->sole();
        $this->assertSame(BetStatus::Active, $bet->status);
    }
}
