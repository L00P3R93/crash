<?php

namespace App\Filament\Widgets;

use App\Enums\BetStatus;
use App\Enums\RoundStatus;
use App\Models\AviatorBet;
use App\Models\AviatorRound;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Current round status, elapsed time, live-computed multiplier — polling,
 * not websocket, to keep the admin panel simple (project-structure doc §6).
 */
class LiveRoundWidget extends BaseWidget
{
    protected static ?int $sort = 50;

    protected int|string|array $columnSpan = 4;

    protected ?string $pollingInterval = '5s';

    protected ?string $heading = 'Live Round';

    protected ?string $description = 'What round is running right now, and how much action is on it.';

    protected function getStats(): array
    {
        $round = AviatorRound::query()->latest('id')->first();

        if (! $round) {
            return [
                Stat::make('Current round', 'No rounds yet')->icon('heroicon-o-rocket-launch'),
            ];
        }

        return [
            Stat::make('Current round', "#{$round->round_number}")
                ->description(ucfirst($round->status->value))
                ->icon('heroicon-o-rocket-launch')
                ->color(match ($round->status) {
                    RoundStatus::Running => 'warning',
                    RoundStatus::Crashed => 'danger',
                    RoundStatus::Settled => 'success',
                    default => 'gray',
                }),
            Stat::make('Started', $round->started_at?->diffForHumans() ?? '—')
                ->icon('heroicon-o-clock'),
            Stat::make('Bets placed', (string) $round->bets()->count())
                ->icon('heroicon-o-ticket'),
            Stat::make('Bets in play', (string) AviatorBet::query()->where('status', BetStatus::Active)->count())
                ->description('Bets currently active, mid-round')
                ->icon('heroicon-o-users')
                ->color('info'),
        ];
    }
}
