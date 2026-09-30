<?php

namespace App\Filament\Resources\GeoViewerResource\Pages;

use App\Filament\Clusters\Geography\Pages\CreateGeoViewer;
use App\Filament\Resources\GeoViewerResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListGeoViewers extends ListRecords
{
    protected static string $resource = GeoViewerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Crear geovisor')->icon(Heroicon::OutlinedPlus)->url(CreateGeoViewer::getUrl())->visible(fn (): bool => auth()->user()?->canManageOpenDataSources() ?? false),
            Action::make('public')->label('Portal ciudadano')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->url(route('geo-viewers.demo'))->openUrlInNewTab()->color('gray'),
        ];
    }
}
