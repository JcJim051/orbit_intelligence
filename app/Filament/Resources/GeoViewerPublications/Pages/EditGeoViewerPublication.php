<?php

namespace App\Filament\Resources\GeoViewerPublications\Pages;

use App\Filament\Resources\GeoViewerPublications\GeoViewerPublicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditGeoViewerPublication extends EditRecord
{
    protected static string $resource = GeoViewerPublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('preview')->label('Vista previa')->url(fn () => route('geo-viewers.embed', $this->record->viewer))->openUrlInNewTab()];
    }
}
