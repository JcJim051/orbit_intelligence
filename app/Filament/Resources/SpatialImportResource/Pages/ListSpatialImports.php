<?php

namespace App\Filament\Resources\SpatialImportResource\Pages;

use App\Filament\Pages\Workspace;
use App\Filament\Resources\SpatialImportResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListSpatialImports extends ListRecords
{
    protected static string $resource = SpatialImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('authorize')->label('Autorizar carga')->icon(Heroicon::OutlinedPlus)->url(Workspace::getUrl(['workspace' => 'cargas-qgis']).'#nueva-importacion'),
        ];
    }
}
