<?php

namespace App\Filament\Widgets;

use App\Enums\BetStatus;
use App\Models\AviatorBet;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Stakes vs payouts vs house margin over time, to sanity-check actual RTP
 * against the configured rate (project-structure doc §6).
 */
class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 80;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Stakes vs payouts (last 14 days)';

    protected ?string $description = 'Stakes collected vs payouts made, over time.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $days = collect(range(13, 0))->map(fn (int $i) => Carbon::now()->subDays($i)->toDateString());

        $staked = AviatorBet::query()
            ->selectRaw('DATE(placed_at) as day, SUM(stake) as total')
            ->where('placed_at', '>=', Carbon::now()->subDays(14))
            ->groupBy('day')
            ->pluck('total', 'day');

        $paid = AviatorBet::query()
            ->selectRaw('DATE(placed_at) as day, SUM(payout) as total')
            ->where('status', BetStatus::Won)
            ->where('placed_at', '>=', Carbon::now()->subDays(14))
            ->groupBy('day')
            ->pluck('total', 'day');

        return [
            'datasets' => [
                [
                    'label' => 'Staked',
                    'data' => $days->map(fn (string $day) => (float) ($staked[$day] ?? 0))->all(),
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Paid out',
                    'data' => $days->map(fn (string $day) => (float) ($paid[$day] ?? 0))->all(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $days->map(fn (string $day) => Carbon::parse($day)->format('M j'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
