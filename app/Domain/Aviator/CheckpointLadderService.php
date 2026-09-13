<?php

namespace App\Domain\Aviator;

use App\Enums\LadderChoice;
use App\Models\AviatorBet;
use App\Models\AviatorBetLadderStep;
use App\Models\LadderConfig;

/**
 * The USSD (or any menu-driven) presentation layer over a round's already-
 * generated, already-stored crash point (architecture doc §27). Never
 * touches or regenerates the crash point — only decides what to show and
 * records the player's choices.
 */
class CheckpointLadderService
{
    public function activeConfig(): LadderConfig
    {
        return LadderConfig::query()->where('is_active', true)->first()
            ?? LadderConfig::query()->firstOrCreate(
                ['name' => 'default'],
                ['safe_ratio' => 1.10, 'risky_ratio' => 2.00, 'is_active' => true],
            );
    }

    /**
     * @return array{safe: array{target: float, probability: float}, risky: array{target: float, probability: float}}
     */
    public function nextRungs(float $current, float $houseEdge, float $safeRatio, float $riskyRatio): array
    {
        $safe = $this->round2($current * $safeRatio);
        $risky = $this->round2($current * $riskyRatio);

        return [
            'safe' => [
                'target' => $safe,
                'probability' => $this->conditionalProbability($current, $safe, $houseEdge),
            ],
            'risky' => [
                'target' => $risky,
                'probability' => $this->conditionalProbability($current, $risky, $houseEdge),
            ],
        ];
    }

    /**
     * P(reach $next | already reached $current). At the 1.00x baseline this
     * reduces to the raw survival formula because P(X >= 1.00) = 1.
     */
    public function conditionalProbability(float $current, float $next, float $houseEdge): float
    {
        if ($current <= 1.00) {
            return round((1 - $houseEdge) / $next, 4);
        }

        return round($current / $next, 4);
    }

    /**
     * Resolve one ladder step against the round's real, already-stored crash
     * multiplier. Never regenerates it.
     */
    public function resolveStep(float $crashMultiplier, float $chosenRung): bool
    {
        return $crashMultiplier >= $chosenRung;
    }

    public function recordStep(
        AviatorBet $bet,
        float $fromMultiplier,
        ?float $toMultiplier,
        LadderChoice $choice,
        ?float $probabilityShown,
        ?bool $survived = null,
    ): AviatorBetLadderStep {
        $nextStepNumber = ((int) $bet->ladderSteps()->max('step_number')) + 1;

        return $bet->ladderSteps()->create([
            'step_number' => $nextStepNumber,
            'from_multiplier' => $fromMultiplier,
            'to_multiplier' => $toMultiplier,
            'choice' => $choice,
            'probability_shown' => $probabilityShown,
            'survived' => $survived,
            'resolved_at' => $survived === null ? null : now(),
        ]);
    }

    private function round2(float $value): float
    {
        return floor($value * 100) / 100;
    }
}
