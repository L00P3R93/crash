<?php

namespace App\Filament\Resources\AviatorRounds;

use App\Enums\RoundStatus;
use App\Filament\Resources\AviatorRounds\Pages\ListAviatorRounds;
use App\Models\AviatorRound;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AviatorRoundResource extends Resource
{
    protected static ?string $model = AviatorRound::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rocket-launch';

    protected static string|UnitEnum|null $navigationGroup = 'Game';

    protected static ?string $recordTitleAttribute = 'round_number';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('round_number')->sortable()->searchable(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => RoundStatus::Scheduled->value,
                        'info' => RoundStatus::Betting->value,
                        'warning' => RoundStatus::Running->value,
                        'danger' => RoundStatus::Crashed->value,
                        'success' => RoundStatus::Settled->value,
                    ]),
                TextColumn::make('crash_multiplier')->suffix('x')->sortable(),
                TextColumn::make('server_seed_hash')->label('Seed hash')->limit(16)->fontFamily('mono')->copyable(),
                TextColumn::make('bets_count')->label('Bets'),
                TextColumn::make('bets_sum_stake')->money('KES')->label('Total staked'),
                TextColumn::make('bets_sum_payout')->money('KES')->label('Total paid'),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('settled_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    array_combine(
                        array_map(fn ($case) => $case->value, RoundStatus::cases()),
                        array_map(fn ($case) => $case->name, RoundStatus::cases()),
                    )
                ),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount('bets')
            ->withSum('bets', 'stake')
            ->withSum('bets', 'payout');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAviatorRounds::route('/'),
        ];
    }
}
