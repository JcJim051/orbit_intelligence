<?php

namespace App\Filament\Resources\SiidEquivalenciaAreas\Pages;

use App\Filament\Resources\SiidEquivalenciaAreas\SiidEquivalenciaAreaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiidEquivalenciaArea extends EditRecord
{
    protected static string $resource = SiidEquivalenciaAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
