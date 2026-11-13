<?php

namespace App\Filament\Resources\SiidSincronizacions\Pages;

use App\Filament\Resources\SiidSincronizacions\SiidSincronizacionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSiidSincronizacion extends EditRecord
{
    protected static string $resource = SiidSincronizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
