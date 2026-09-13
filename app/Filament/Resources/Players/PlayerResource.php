<?php

namespace App\Filament\Resources\Players;

use App\Enums\PlayerStatus;
use App\Filament\Resources\Players\Pages\EditPlayer;
use App\Filament\Resources\Players\Pages\ListPlayers;
use App\Models\Player;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Game players (msisdn-identified) — separate from the Fortify `users` staff
 * table. No create page: players are created silently on first USSD/web
 * contact, never by staff.
 */
class PlayerResource extends Resource
{
    protected static ?string $model = Player::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Players';

    protected static ?string $recordTitleAttribute = 'msisdn';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->description('How this player is identified — the phone number is fixed at first contact.')
                ->icon('heroicon-o-identification')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('msisdn')
                            ->label('Phone number')
                            ->prefixIcon('heroicon-o-device-phone-mobile')
                            ->required()
                            ->disabled(),
                        TextInput::make('name')
                            ->prefixIcon('heroicon-o-user')
                            ->placeholder('Not provided')
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('Account Status & Limits')
                ->description('Suspending or banning a player also blocks new bets; existing limits are enforced per calendar day.')
                ->icon('heroicon-o-shield-exclamation')
                ->schema([
                    Select::make('status')
                        ->native(false)
                        ->options([
                            PlayerStatus::Active->value => 'Active',
                            PlayerStatus::Suspended->value => 'Suspended',
                            PlayerStatus::Banned->value => 'Banned',
                        ])
                        ->required(),
                    Grid::make(2)->schema([
                        TextInput::make('daily_stake_limit')
                            ->label('Daily stake limit')
                            ->numeric()
                            ->prefix('KSh')
                            ->placeholder('No limit')
                            ->nullable(),
                        TextInput::make('daily_deposit_limit')
                            ->label('Daily deposit limit')
                            ->numeric()
                            ->prefix('KSh')
                            ->placeholder('No limit')
                            ->nullable(),
                    ]),
                ]),

            Section::make('Responsible Gambling')
                ->description('Self-exclusion locks the player out of betting until this date/time passes.')
                ->icon('heroicon-o-hand-raised')
                ->schema([
                    DateTimePicker::make('self_excluded_until')
                        ->label('Self-excluded until')
                        ->native(false)
                        ->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('msisdn')->searchable(),
                TextColumn::make('name')->searchable()->placeholder('—'),
                IconColumn::make('is_bot')->label('Bot')->boolean(),
                TextColumn::make('wallet.balance')->label('Balance')->money('KES'),
                BadgeColumn::make('status')
                    ->colors([
                        'success' => PlayerStatus::Active->value,
                        'warning' => PlayerStatus::Suspended->value,
                        'danger' => PlayerStatus::Banned->value,
                    ]),
                TextColumn::make('self_excluded_until')->dateTime()->placeholder('—')->label('Self-excluded until'),
                TextColumn::make('created_at')->dateTime()->sortable()->label('Joined'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    PlayerStatus::Active->value => 'Active',
                    PlayerStatus::Suspended->value => 'Suspended',
                    PlayerStatus::Banned->value => 'Banned',
                ]),
                TernaryFilter::make('is_bot')->label('Bot accounts'),
            ])
            ->recordActions([
                Action::make('suspend')
                    ->visible(fn (Player $record) => $record->status === PlayerStatus::Active)
                    ->requiresConfirmation()
                    ->color('warning')
                    ->action(fn (Player $record) => $record->forceFill(['status' => PlayerStatus::Suspended])->save()),
                Action::make('ban')
                    ->visible(fn (Player $record) => $record->status !== PlayerStatus::Banned)
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(fn (Player $record) => $record->forceFill(['status' => PlayerStatus::Banned])->save()),
                Action::make('reactivate')
                    ->visible(fn (Player $record) => $record->status !== PlayerStatus::Active)
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(fn (Player $record) => $record->forceFill(['status' => PlayerStatus::Active])->save()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlayers::route('/'),
            'edit' => EditPlayer::route('/{record}/edit'),
        ];
    }
}
