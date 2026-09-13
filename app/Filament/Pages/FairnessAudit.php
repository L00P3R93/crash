<?php

namespace App\Filament\Pages;

use App\Domain\Aviator\ProvablyFairService;
use App\Models\AviatorRound;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Look up any round by number and recompute its crash point server-side from
 * the revealed seed — the internal counterpart to the player-facing
 * FairnessVerifyScreen in USSD (project-structure doc §6).
 *
 * @property-read Schema $form
 */
class FairnessAudit extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Game';

    protected string $view = 'filament.pages.fairness-audit';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?AviatorRound $round = null;

    public ?bool $verified = null;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verify a Round')
                    ->description('Look up any settled round and recompute its crash point server-side from the revealed seed.')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([
                        TextInput::make('round_number')
                            ->numeric()
                            ->required()
                            ->prefixIcon('heroicon-o-hashtag')
                            ->label('Round number'),
                    ])->columns(2)->columnSpan(['lg' => 2]),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function lookup(): void
    {
        $data = $this->form->getState();

        $this->round = AviatorRound::query()->where('round_number', $data['round_number'])->first();
        $this->verified = null;

        if (! $this->round) {
            Notification::make()->title('No round with that number.')->danger()->send();
        }
    }

    public function recompute(ProvablyFairService $provablyFair): void
    {
        if (! $this->round || $this->round->server_seed === null) {
            Notification::make()->title('Server seed not yet revealed — round has not settled.')->warning()->send();

            return;
        }

        $this->verified = $provablyFair->verify(
            serverSeed: $this->round->server_seed,
            expectedServerSeedHash: $this->round->server_seed_hash,
            clientSeed: $this->round->client_seed,
            nonce: $this->round->nonce,
            houseEdge: (float) $this->round->house_edge,
            expectedCrashMultiplier: (float) $this->round->crash_multiplier,
        );

        Notification::make()
            ->title($this->verified ? 'Verified — the crash point reproduces exactly.' : 'Mismatch — this round does not verify.')
            ->color($this->verified ? 'success' : 'danger')
            ->send();
    }
}
