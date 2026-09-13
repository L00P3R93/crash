<?php

namespace App\Filament\Resources\Wallets;

use App\Filament\Resources\Wallets\Pages\ListWallets;
use App\Models\Wallet;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only balance view. Corrections never edit a balance in place — they
 * go through a new `adjustment`-type wallet_transactions row instead
 * (architecture doc §18), so there is deliberately no edit/create page here.
 */
class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static string|UnitEnum|null $navigationGroup = 'Players';

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('player.msisdn')->label('Player')->searchable(),
                IconColumn::make('player.is_bot')->label('Bot')->boolean(),
                TextColumn::make('balance')->money('KES')->sortable(),
                TextColumn::make('currency'),
                TextColumn::make('updated_at')->dateTime()->sortable()->label('Last activity'),
            ])
            ->filters([
                TernaryFilter::make('is_bot')
                    ->label('Bot accounts')
                    ->queries(
                        true: fn ($query) => $query->whereHas('player', fn ($q) => $q->where('is_bot', true)),
                        false: fn ($query) => $query->whereHas('player', fn ($q) => $q->where('is_bot', false)),
                    ),
            ])
            ->defaultSort('updated_at', 'desc');
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
            'index' => ListWallets::route('/'),
        ];
    }
}
