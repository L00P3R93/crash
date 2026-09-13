<?php

namespace App\Filament\Resources\LadderConfigs\Pages;

use App\Filament\Resources\LadderConfigs\LadderConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLadderConfigs extends ListRecords
{
    protected static string $resource = LadderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
