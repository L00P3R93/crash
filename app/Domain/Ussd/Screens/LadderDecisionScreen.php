<?php

namespace App\Domain\Ussd\Screens;

use App\Domain\Aviator\AviatorGameService;
use App\Domain\Aviator\BetService;
use App\Domain\Aviator\CheckpointLadderService;
use App\Domain\Aviator\Exceptions\RoundClosedException;
use App\Domain\Ussd\AbstractUssdScreen;
use App\Domain\Ussd\UssdResponse;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Enums\LadderChoice;
use App\Models\AviatorBet;
use App\Models\UssdSession;
use Illuminate\Support\Str;

/**
 * Presentation layer over a round's already-generated, already-stored crash
 * point (architecture doc §27) — every rung decision is resolved by
 * comparing the chosen target against `round.crash_multiplier` directly,
 * regardless of the round's real-time progress. The USSD player never
 * watches a live multiplier; there's nothing to watch.
 */
class LadderDecisionScreen extends AbstractUssdScreen
{
    public function __construct(
        private readonly AviatorGameService $game = new AviatorGameService,
        private readonly CheckpointLadderService $ladder = new CheckpointLadderService,
        private readonly BetService $bets = new BetService,
    ) {}

    public function handle(UssdSession $session, string $input): UssdResponse
    {
        $state = $session->state ?? [];

        if ($input === '') {
            return $this->stay($this->renderDecision($session));
        }

        if (($state['awaiting'] ?? null) === 'post_result') {
            return $this->handlePostResult($session, $input);
        }

        if ($input === '0') {
            return $this->goTo($session, 'main_menu');
        }

        $bet = AviatorBet::query()->findOrFail((int) $state['bet_id']);
        $round = $bet->round;
        $currentRung = (float) ($state['current_rung'] ?? 1.00);
        $maxMultiplier = (float) config('aviator.max_multiplier');
        $atMax = $currentRung >= $maxMultiplier;

        if ($input === '1' && $currentRung > 1.00) {
            $bet = $this->game->cashOut($bet->id, atMultiplier: $currentRung);

            return $this->stay($this->renderWon($session, $bet, (float) $round->crash_multiplier));
        }

        if ($atMax) {
            return $this->invalidChoice($this->renderDecision($session));
        }

        $rungs = $this->computeRungs($currentRung, $maxMultiplier);

        $choice = match ($input) {
            '2' => ['key' => LadderChoice::ContinueSafe, ...$rungs['safe']],
            '3' => ['key' => LadderChoice::RocketRisky, ...$rungs['risky']],
            default => null,
        };

        if ($choice === null) {
            return $this->invalidChoice($this->renderDecision($session));
        }

        $survived = $this->ladder->resolveStep((float) $round->crash_multiplier, $choice['target']);

        $this->ladder->recordStep($bet, $currentRung, $choice['target'], $choice['key'], $choice['probability'], $survived);

        if (! $survived) {
            $this->bets->markLost($bet->id);

            $session->state = ['awaiting' => 'post_result', 'stake' => $state['stake']];
            $balance = (float) $session->player->wallet->fresh()->balance;

            return $this->stay(
                "CRASHED at {$this->fmtMult((float) $round->crash_multiplier)}x\n".
                "Lost KSh {$this->fmt((float) $state['stake'])}\n".
                "Balance KSh {$this->fmt($balance)}\n".
                "1: Play Again KSh {$this->fmt((float) $state['stake'])}\n".
                '2: Change Bet'."\n".
                '0: Menu'
            );
        }

        $session->state = array_merge($state, ['current_rung' => $choice['target']]);

        return $this->stay($this->renderDecision($session));
    }

    /**
     * @return array{safe: array{target: float, probability: float}, risky: array{target: float, probability: float}}
     */
    private function computeRungs(float $currentRung, float $maxMultiplier): array
    {
        $config = $this->ladder->activeConfig();
        $houseEdge = (float) config('aviator.house_edge');

        $rungs = $this->ladder->nextRungs($currentRung, $houseEdge, (float) $config->safe_ratio, (float) $config->risky_ratio);

        return [
            'safe' => ['target' => min($rungs['safe']['target'], $maxMultiplier), 'probability' => $rungs['safe']['probability']],
            'risky' => ['target' => min($rungs['risky']['target'], $maxMultiplier), 'probability' => $rungs['risky']['probability']],
        ];
    }

    private function renderDecision(UssdSession $session): string
    {
        $state = $session->state;
        $currentRung = (float) ($state['current_rung'] ?? 1.00);
        $stake = (float) $state['stake'];
        $maxMultiplier = (float) config('aviator.max_multiplier');
        $collectAmount = round($stake * $currentRung, 2);

        if ($currentRung >= $maxMultiplier) {
            return "Rocket reached {$this->fmtMult($currentRung)}x - Max reached!\n".
                "Collect KSh {$this->fmt($collectAmount)}\n".
                "1: Cash Out\n0: Menu";
        }

        $rungs = $this->computeRungs($currentRung, $maxMultiplier);
        $safe = $rungs['safe'];
        $risky = $rungs['risky'];
        // Truncated, not rounded, to match the observed USSD copy (architecture
        // doc §27.1/§27.3: 0.485 displays as "48 pct", not "49 pct").
        $safePct = (int) floor($safe['probability'] * 100);
        $riskyPct = (int) floor($risky['probability'] * 100);

        if ($currentRung <= 1.00) {
            return "Stake KSh {$this->fmt($stake)} | Now 1.00x\n".
                "2: Continue to {$this->fmtMult($safe['target'])}x | {$safePct} pct chance\n".
                "3: Rocket to {$this->fmtMult($risky['target'])}x | {$riskyPct} pct chance\n".
                '0: Menu';
        }

        return "Rocket reached {$this->fmtMult($currentRung)}x\n".
            "Collect KSh {$this->fmt($collectAmount)} - not paid\n".
            "1: Cash Out\n".
            "2: Continue to {$this->fmtMult($safe['target'])}x | {$safePct} pct chance\n".
            "3: Rocket to {$this->fmtMult($risky['target'])}x | {$riskyPct} pct chance\n".
            '0: Menu';
    }

    private function renderWon(UssdSession $session, AviatorBet $bet, float $crashMultiplier): string
    {
        $session->state = ['awaiting' => 'post_result', 'stake' => (float) $bet->stake];

        $paid = (float) $bet->payout;
        $net = $paid - (float) $bet->stake;
        $balance = (float) $session->player->wallet->fresh()->balance;

        return "WON\n".
            "Cash Out {$this->fmtMult((float) $bet->cashout_multiplier)}x | Crash {$this->fmtMult($crashMultiplier)}x\n".
            "Paid KSh {$this->fmt($paid)} | Net +KSh {$this->fmt($net)}\n".
            "Balance KSh {$this->fmt($balance)}\n".
            "1: Play Again KSh {$this->fmt((float) $bet->stake)}\n".
            "2: Change Bet\n".
            '0: Menu';
    }

    private function handlePostResult(UssdSession $session, string $input): UssdResponse
    {
        $stake = (float) $session->state['stake'];

        return match ($input) {
            '1' => $this->playAgain($session, $stake),
            '2' => $this->goTo($session, 'change_bet'),
            '0' => $this->goTo($session, 'main_menu'),
            default => $this->invalidChoice("1: Play Again KSh {$this->fmt($stake)}\n2: Change Bet\n0: Menu"),
        };
    }

    private function playAgain(UssdSession $session, float $stake): UssdResponse
    {
        try {
            $bet = $this->game->placeBet($session->player, $stake, 'ussd', 'AVIATOR-BET-'.Str::upper(Str::random(16)));
        } catch (RoundClosedException) {
            return $this->stay("No round is open for betting right now.\nPlease wait for the next round.\n0: Menu");
        } catch (InsufficientFundsException) {
            $balance = (float) $session->player->wallet->fresh()->balance;

            return $this->stay("Insufficient balance.\nBalance KSh {$this->fmt($balance)}\n2: Top Up\n0: Menu");
        }

        $session->state = ['bet_id' => $bet->id, 'stake' => $stake];

        return $this->stay($this->renderDecision($session));
    }
}
