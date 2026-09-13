<?php

namespace App\Filament\Resources\LadderConfigs\Pages;

use App\Filament\Resources\LadderConfigs\LadderConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLadderConfig extends EditRecord
{
    protected static string $resource = LadderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
