<?php

namespace App\Filament\Resources\AviatorBets\Pages;

use App\Enums\BetStatus;
use App\Filament\Resources\AviatorBets\AviatorBetResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewAviatorBet extends ViewRecord
{
    protected static string $resource = AviatorBetResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Bet')
                ->icon('heroicon-o-ticket')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('bet_reference')->label('Reference')->copyable(),
                        TextEntry::make('round.round_number')->label('Round')->icon('heroicon-o-rocket-launch'),
                        TextEntry::make('player.msisdn')->label('Player')->icon('heroicon-o-user'),
                    ]),
                    Grid::make(3)->schema([
                        TextEntry::make('channel')->badge(),
                        TextEntry::make('stake')->money('KES'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (BetStatus $state) => match ($state) {
                                BetStatus::Pending, BetStatus::Cancelled => 'gray',
                                BetStatus::Active => 'info',
                                BetStatus::Won => 'success',
                                BetStatus::Lost => 'danger',
                            }),
                    ]),
                ]),

            Section::make('Outcome')
                ->icon('heroicon-o-flag')
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('current_rung')->suffix('x')->label('Last rung reached')->placeholder('—'),
                        TextEntry::make('cashout_multiplier')->suffix('x')->placeholder('—'),
                        TextEntry::make('payout')->money('KES')->placeholder('—'),
                    ]),
                    Grid::make(2)->schema([
                        TextEntry::make('placed_at')->dateTime()->icon('heroicon-o-clock'),
                        TextEntry::make('cashed_out_at')->dateTime()->placeholder('—')->icon('heroicon-o-clock'),
                    ]),
                ]),
        ]);
    }
}
