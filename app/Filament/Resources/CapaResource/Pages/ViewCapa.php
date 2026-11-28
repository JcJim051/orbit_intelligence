<?php

namespace App\Filament\Resources\CapaResource\Pages;

use App\Filament\Resources\CapaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCapa extends ViewRecord
{
    protected static string $resource = CapaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Editar'),
        ];
    }
}
