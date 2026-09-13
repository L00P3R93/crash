<?php

namespace App\Filament\Resources\LadderConfigs;

use App\Filament\Resources\LadderConfigs\Pages\CreateLadderConfig;
use App\Filament\Resources\LadderConfigs\Pages\EditLadderConfig;
use App\Filament\Resources\LadderConfigs\Pages\ListLadderConfigs;
use App\Models\LadderConfig;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The USSD ladder's safe/risky rung ratios (architecture doc §27.6) — a
 * product/paytable decision, not derived from the RNG, so it's editable here.
 */
class LadderConfigResource extends Resource
{
    protected static ?string $model = LadderConfig::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Game';

    protected static ?string $navigationLabel = 'Ladder Configs';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ladder Configuration')
                ->description('Rung spacing for the USSD checkpoint ladder — a paytable decision, not derived from the RNG.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->schema([
                    TextInput::make('name')
                        ->prefixIcon('heroicon-o-tag')
                        ->required()
                        ->maxLength(255),
                    Grid::make(2)->schema([
                        TextInput::make('safe_ratio')
                            ->label('Safe ratio ("Continue")')
                            ->numeric()->required()->step(0.01)->minValue(1.01)
                            ->suffix('×')
                            ->helperText('Modest step multiplier vs the current rung, e.g. 1.10'),
                        TextInput::make('risky_ratio')
                            ->label('Risky ratio ("Rocket")')
                            ->numeric()->required()->step(0.01)->minValue(1.01)
                            ->suffix('×')
                            ->helperText('Aggressive step multiplier vs the current rung, e.g. 2.00'),
                    ]),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Only one active config is used at a time by the checkpoint ladder.')
                        ->inline(false),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('safe_ratio')->suffix('x'),
                TextColumn::make('risky_ratio')->suffix('x'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLadderConfigs::route('/'),
            'create' => CreateLadderConfig::route('/create'),
            'edit' => EditLadderConfig::route('/{record}/edit'),
        ];
    }
}
