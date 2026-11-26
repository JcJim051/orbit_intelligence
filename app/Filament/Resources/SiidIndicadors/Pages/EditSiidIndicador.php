<?php

namespace App\Filament\Resources\SiidIndicadors\Pages;

use App\Filament\Resources\SiidIndicadors\SiidIndicadorResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiidIndicador extends EditRecord
{
    protected static string $resource = SiidIndicadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
