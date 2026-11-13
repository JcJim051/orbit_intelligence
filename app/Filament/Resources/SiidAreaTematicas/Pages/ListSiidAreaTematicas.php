<?php

namespace App\Filament\Resources\SiidAreaTematicas\Pages;

use App\Filament\Resources\SiidAreaTematicas\SiidAreaTematicaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiidAreaTematicas extends ListRecords
{
    protected static string $resource = SiidAreaTematicaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
