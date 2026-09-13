<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Filament\Resources\WalletTransactions\Pages\ListWalletTransactions;
use App\Models\WalletTransaction;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The full ledger. Read-only, never editable — corrections are new
 * `adjustment` rows, never in-place edits (architecture doc §18).
 */
class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|UnitEnum|null $navigationGroup = 'Players';

    protected static ?string $navigationLabel = 'Ledger';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->searchable(),
                TextColumn::make('wallet.player.msisdn')->label('Player')->searchable(),
                BadgeColumn::make('type'),
                TextColumn::make('amount')->money('KES')->sortable()
                    ->color(fn (WalletTransaction $record) => $record->amount >= 0 ? 'success' : 'danger'),
                TextColumn::make('balance_after')->money('KES')->label('Balance after'),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => WalletTransactionStatus::Completed->value,
                        'warning' => WalletTransactionStatus::Pending->value,
                        'danger' => WalletTransactionStatus::Failed->value,
                        'gray' => WalletTransactionStatus::Reversed->value,
                    ]),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(
                    array_combine(
                        array_map(fn ($case) => $case->value, WalletTransactionType::cases()),
                        array_map(fn ($case) => $case->name, WalletTransactionType::cases()),
                    )
                ),
                SelectFilter::make('status')->options(
                    array_combine(
                        array_map(fn ($case) => $case->value, WalletTransactionStatus::cases()),
                        array_map(fn ($case) => $case->name, WalletTransactionStatus::cases()),
                    )
                ),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc');
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
        return parent::getEloquentQuery()->with('wallet.player');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWalletTransactions::route('/'),
        ];
    }
}
