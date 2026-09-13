<?php

namespace App\Filament\Resources\AviatorBets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LadderStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'ladderSteps';

    protected static ?string $title = 'Ladder steps';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('step_number')->label('#'),
                TextColumn::make('from_multiplier')->suffix('x'),
                TextColumn::make('to_multiplier')->suffix('x')->placeholder('cash out'),
                TextColumn::make('choice')->badge(),
                TextColumn::make('probability_shown')->label('Shown probability')
                    ->formatStateUsing(fn (?string $state) => $state === null ? '—' : round(((float) $state) * 100).'%'),
                IconColumn::make('survived')->boolean()->placeholder('—'),
                TextColumn::make('resolved_at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('step_number');
    }

    protected function canCreate(): bool
    {
        return false;
    }

    protected function canEdit(Model $record): bool
    {
        return false;
    }

    protected function canDelete(Model $record): bool
    {
        return false;
    }
}
