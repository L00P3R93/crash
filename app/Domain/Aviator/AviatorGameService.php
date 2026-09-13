<?php

namespace App\Domain\Aviator;

use App\Enums\RoundStatus;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use App\Models\Player;

/**
 * The single entry point Web and USSD controllers call into — neither talks
 * to BetService/CashoutService/RoundService directly, so the game math is
 * never duplicated per channel (architecture doc §15).
 */
class AviatorGameService
{
    public function __construct(
        private readonly BetService $bets = new BetService,
        private readonly CashoutService $cashouts = new CashoutService,
    ) {}

    public function placeBet(
        Player $player,
        float $stake,
        string $channel,
        string $betReference,
        ?float $autoCashout = null,
    ): AviatorBet {
        return $this->bets->placeBet($player, $stake, $channel, $betReference, $autoCashout);
    }

    public function cashOut(int $betId, ?float $atMultiplier = null): AviatorBet
    {
        return $this->cashouts->cashOut($betId, $atMultiplier);
    }

    public function getCurrentRound(): ?AviatorRound
    {
        return AviatorRound::query()
            ->whereIn('status', [RoundStatus::Scheduled, RoundStatus::Betting, RoundStatus::Running])
            ->latest('id')
            ->first();
    }

    public function getBetStatus(string $betReference): ?AviatorBet
    {
        return AviatorBet::query()->where('bet_reference', $betReference)->first();
    }
}
