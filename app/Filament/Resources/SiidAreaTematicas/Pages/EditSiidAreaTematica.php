<?php

namespace App\Filament\Resources\SiidAreaTematicas\Pages;

use App\Filament\Resources\SiidAreaTematicas\SiidAreaTematicaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiidAreaTematica extends EditRecord
{
    protected static string $resource = SiidAreaTematicaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
