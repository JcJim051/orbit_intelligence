<?php

namespace App\Filament\Resources\SiidEquivalenciaAreas\Pages;

use App\Filament\Resources\SiidEquivalenciaAreas\SiidEquivalenciaAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiidEquivalenciaAreas extends ListRecords
{
    protected static string $resource = SiidEquivalenciaAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
