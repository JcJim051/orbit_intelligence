<?php

namespace App\Filament\Resources\PublicToolPublications\Pages;

use App\Filament\Resources\PublicToolPublications\PublicToolPublicationResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditPublicToolPublication extends EditRecord
{
    protected static string $resource = PublicToolPublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('preview')->label('Vista pública')->url(route('micrositio.analisis-geoespacial.index'))->openUrlInNewTab()->visible(fn () => (bool) $this->record->visible)];
    }
}
