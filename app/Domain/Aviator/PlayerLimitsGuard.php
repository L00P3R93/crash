<?php

namespace App\Domain\Aviator;

use App\Domain\Aviator\Exceptions\DailyLimitExceededException;
use App\Domain\Aviator\Exceptions\SelfExcludedException;
use App\Domain\Aviator\Exceptions\WithdrawalsDisabledException;
use App\Enums\TopupStatus;
use App\Models\AviatorBet;
use App\Models\Player;
use App\Models\Topup;

/**
 * Responsible-gambling checks: self-exclusion and self-service daily limits
 * on `players`. Checked before a bet is placed or a top-up is credited —
 * never as an after-the-fact correction.
 */
class PlayerLimitsGuard
{
    public function assertCanBet(Player $player, float $stake): void
    {
        $this->assertNotSelfExcluded($player);

        if ($player->daily_stake_limit === null) {
            return;
        }

        $stakedToday = (float) AviatorBet::query()
            ->where('player_id', $player->id)
            ->whereDate('placed_at', today())
            ->sum('stake');

        if ($stakedToday + $stake > (float) $player->daily_stake_limit) {
            throw DailyLimitExceededException::forStake($player->id);
        }
    }

    public function assertCanDeposit(Player $player, float $amount): void
    {
        $this->assertNotSelfExcluded($player);

        if ($player->daily_deposit_limit === null) {
            return;
        }

        $depositedToday = (float) Topup::query()
            ->where('player_id', $player->id)
            ->where('status', TopupStatus::Completed)
            ->whereDate('completed_at', today())
            ->sum('amount');

        if ($depositedToday + $amount > (float) $player->daily_deposit_limit) {
            throw DailyLimitExceededException::forDeposit($player->id);
        }
    }

    /**
     * Bot accounts (App\Models\Player::$is_bot, seeded by
     * database/seeders/BotPlayerSeeder for local/dev activity) never
     * withdraw real money — checked at the actual money-movement boundary
     * (B2cService::initiate()) rather than only in the USSD screen, so any
     * future withdrawal path is covered too.
     */
    public function assertCanWithdraw(Player $player): void
    {
        if ($player->is_bot) {
            throw WithdrawalsDisabledException::forPlayer($player->id);
        }
    }

    private function assertNotSelfExcluded(Player $player): void
    {
        if ($player->isSelfExcluded()) {
            throw SelfExcludedException::forPlayer($player->id);
        }
    }
}
