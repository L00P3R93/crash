<?php

namespace App\Filament\Resources\AviatorBets;

use App\Enums\BetStatus;
use App\Filament\Resources\AviatorBets\Pages\ListAviatorBets;
use App\Filament\Resources\AviatorBets\Pages\ViewAviatorBet;
use App\Filament\Resources\AviatorBets\RelationManagers\LadderStepsRelationManager;
use App\Models\AviatorBet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class AviatorBetResource extends Resource
{
    protected static ?string $model = AviatorBet::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Game';

    protected static ?string $recordTitleAttribute = 'bet_reference';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bet_reference')->label('Reference')->searchable(),
                TextColumn::make('round.round_number')->label('Round')->sortable(),
                TextColumn::make('player.msisdn')->label('Player')->searchable(),
                TextColumn::make('channel')->badge(),
                TextColumn::make('stake')->money('KES')->sortable(),
                TextColumn::make('cashout_multiplier')->suffix('x')->placeholder('—'),
                TextColumn::make('payout')->money('KES')->placeholder('—'),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => [BetStatus::Pending->value, BetStatus::Cancelled->value],
                        'info' => BetStatus::Active->value,
                        'success' => BetStatus::Won->value,
                        'danger' => BetStatus::Lost->value,
                    ]),
                TextColumn::make('placed_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    array_combine(
                        array_map(fn ($case) => $case->value, BetStatus::cases()),
                        array_map(fn ($case) => $case->name, BetStatus::cases()),
                    )
                ),
                SelectFilter::make('channel')->options([
                    'ussd' => 'USSD',
                    'web' => 'Web',
                    'api' => 'API',
                    'bot' => 'Bot',
                ]),
            ])
            ->defaultSort('placed_at', 'desc');
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

    public static function getRelations(): array
    {
        return [
            LadderStepsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['round', 'player']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAviatorBets::route('/'),
            'view' => ViewAviatorBet::route('/{record}'),
        ];
    }
}
