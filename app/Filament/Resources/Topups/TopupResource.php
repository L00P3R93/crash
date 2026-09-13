<?php

namespace App\Filament\Resources\Topups;

use App\Enums\TopupStatus;
use App\Filament\Resources\Topups\Pages\ListTopups;
use App\Models\Topup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TopupResource extends Resource
{
    protected static ?string $model = Topup::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-down-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Payments';

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('player.msisdn')->label('Player')->searchable(),
                TextColumn::make('amount')->money('KES')->sortable(),
                TextColumn::make('provider'),
                TextColumn::make('provider_reference')->label('M-Pesa ref')->searchable()->placeholder('—'),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => TopupStatus::Completed->value,
                        'warning' => TopupStatus::Pending->value,
                        'danger' => TopupStatus::Failed->value,
                        'gray' => TopupStatus::Cancelled->value,
                    ]),
                TextColumn::make('requested_at')->dateTime()->sortable(),
                TextColumn::make('completed_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(
                    array_combine(
                        array_map(fn ($case) => $case->value, TopupStatus::cases()),
                        array_map(fn ($case) => $case->name, TopupStatus::cases()),
                    )
                ),
            ])
            ->defaultSort('requested_at', 'desc');
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
        return parent::getEloquentQuery()->with('player');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTopups::route('/'),
        ];
    }
}
