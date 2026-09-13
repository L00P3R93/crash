<?php

namespace App\Filament\Widgets;

use App\Enums\BetStatus;
use App\Models\AviatorBet;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

/**
 * Mirrors the USSD "Top Pilots" leaderboard (architecture doc §28.2).
 */
class TopPilotsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Pilots';

    protected static ?int $sort = 100;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AviatorBet::query()
                    ->where('status', BetStatus::Won)
                    ->whereNotNull('cashout_multiplier')
                    ->whereHas('player', fn ($query) => $query->where('is_bot', false))
                    ->with('player')
                    ->orderByDesc('cashout_multiplier')
            )
            ->columns([
                TextColumn::make('player.msisdn')->label('Player')->icon('heroicon-o-user'),
                TextColumn::make('cashout_multiplier')->suffix('x')->label('Cashout')
                    ->badge()->color('success')->weight('bold'),
                TextColumn::make('payout')->money('KES')->label('Payout')->sortable(),
                TextColumn::make('placed_at')->dateTime()->label('When')->since(),
            ])
            ->paginated([5]);
    }
}
