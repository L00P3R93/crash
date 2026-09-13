<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Staff/admin accounts (Fortify's `users` table) — separate from `players`.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Staff';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Details')
                ->description('Staff sign-in identity for the admin panel.')
                ->icon('heroicon-o-identification')
                ->schema([
                    TextInput::make('name')
                        ->prefixIcon('heroicon-o-user')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->prefixIcon('heroicon-o-envelope')
                        ->email()
                        ->extraAttributes([
                            'autocomplete' => 'new-password',
                        ])
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                ]),

            Section::make('Security')
                ->description('Leave blank when editing to keep the current password.')
                ->icon('heroicon-o-lock-closed')
                ->schema([
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->extraAttributes([
                            'autocomplete' => 'new-password',
                        ])
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->maxLength(255),
                ]),

            Section::make('Permissions')
                ->description('Admin access grants full control over game settings, staff accounts, and player funds.')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    Toggle::make('is_admin')
                        ->label('Admin access')
                        ->inline(false)
                        ->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                IconColumn::make('is_admin')->boolean()->label('Admin'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
