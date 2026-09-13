<?php

namespace App\Filament\Pages;

use App\Models\GameSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * DB-backed overrides for config/aviator.php (house edge, stake bounds, max
 * multiplier, etc.) — editable without a deploy. A blank field falls back to
 * the .env-driven default; see AppServiceProvider::overlayGameSettings().
 *
 * @property-read Schema $form
 */
class GameSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Game';

    protected string $view = 'filament.pages.game-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(GameSetting::current()->only([
            'house_edge', 'min_stake', 'max_stake', 'max_multiplier',
            'betting_window_seconds', 'post_round_pause_seconds',
            'acceleration_k', 'winnings_tax_rate',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Economics')
                    ->description('The house edge and stake bounds that drive the provably-fair RNG and paytable. Leave any field blank to fall back to the .env default shown as its placeholder.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('house_edge')
                            ->suffixIcon('heroicon-o-percent-badge')
                            ->numeric()->step(0.0001)->placeholder(config('aviator.house_edge'))
                            ->helperText('E.g. 0.03 for a 3% edge.'),
                        Grid::make(2)->schema([
                            TextInput::make('min_stake')
                                ->label('Minimum stake')
                                ->prefix('KSh')
                                ->numeric()->placeholder(config('aviator.min_stake')),
                            TextInput::make('max_stake')
                                ->label('Maximum stake')
                                ->prefix('KSh')
                                ->numeric()->placeholder(config('aviator.max_stake')),
                        ]),
                        TextInput::make('max_multiplier')
                            ->label('Maximum multiplier')
                            ->suffix('×')
                            ->numeric()->placeholder(config('aviator.max_multiplier'))
                            ->helperText('Rounds are capped at this multiplier regardless of the RNG result.'),
                        TextInput::make('winnings_tax_rate')
                            ->suffixIcon('heroicon-o-percent-badge')
                            ->numeric()->step(0.0001)->placeholder(config('aviator.winnings_tax_rate'))
                            ->helperText('Deducted from winning payouts before crediting the wallet.'),
                    ]),

                Section::make('Round Timing')
                    ->description('How long betting stays open, how fast the multiplier climbs, and how long the result stays on screen before the next round opens.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('betting_window_seconds')
                                ->label('Betting window')
                                ->suffix('seconds')
                                ->numeric()->placeholder(config('aviator.betting_window_seconds')),
                            TextInput::make('post_round_pause_seconds')
                                ->label('Post-round pause')
                                ->suffix('seconds')
                                ->numeric()->placeholder(config('aviator.post_round_pause_seconds'))
                                ->helperText('How long the crash/win/loss result stays visible before the next round\'s betting window opens.'),
                        ]),
                        TextInput::make('acceleration_k')
                            ->label('Acceleration (k)')
                            ->numeric()->step(0.0001)->placeholder(config('aviator.acceleration_k'))
                            ->helperText('M(t) = e^(k·t) — controls how fast the multiplier climbs.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        GameSetting::current()->forceFill($this->form->getState())->save();

        Notification::make()
            ->title('Settings saved')
            ->body('Takes effect on the next request — no deploy needed.')
            ->success()
            ->send();
    }
}
