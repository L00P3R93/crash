<?php

namespace App\Filament\Resources\UssdSessions;

use App\Enums\UssdSessionStatus;
use App\Filament\Resources\UssdSessions\Pages\ListUssdSessions;
use App\Models\UssdSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Debug view for support staff — see a player's current USSD menu state live.
 */
class UssdSessionResource extends Resource
{
    protected static ?string $model = UssdSession::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'USSD Sessions';

    protected static ?string $recordTitleAttribute = 'session_id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('msisdn')->searchable(),
                TextColumn::make('current_screen')->badge(),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => UssdSessionStatus::Active->value,
                        'gray' => [UssdSessionStatus::Completed->value, UssdSessionStatus::Cancelled->value],
                        'warning' => UssdSessionStatus::Expired->value,
                    ]),
                TextColumn::make('last_input')->label('Last input')->placeholder('—'),
                TextColumn::make('started_at')->dateTime()->sortable(),
                TextColumn::make('last_interaction_at')->dateTime()->sortable()->label('Last activity'),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    array_combine(
                        array_map(fn ($case) => $case->value, UssdSessionStatus::cases()),
                        array_map(fn ($case) => $case->name, UssdSessionStatus::cases()),
                    )
                ),
            ])
            ->defaultSort('last_interaction_at', 'desc');
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

    public static function getPages(): array
    {
        return [
            'index' => ListUssdSessions::route('/'),
        ];
    }
}
