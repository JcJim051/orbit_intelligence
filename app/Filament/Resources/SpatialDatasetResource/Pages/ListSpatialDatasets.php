<?php

namespace App\Filament\Resources\SpatialDatasetResource\Pages;

use App\Filament\Pages\Workspace;
use App\Filament\Resources\SpatialDatasetResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSpatialDatasets extends ListRecords
{
    protected static string $resource = SpatialDatasetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('advanced')->label('Gestión avanzada')->icon(Heroicon::OutlinedCog6Tooth)->url(Workspace::getUrl(['workspace' => 'catalogo-datos'])),
        ];
    }
}
