<?php

namespace App\Filament\Resources\SiidPublicacions\Pages;

use App\Filament\Resources\SiidPublicacions\SiidPublicacionResource;
use Filament\Resources\Pages\EditRecord;

class EditSiidPublicacion extends EditRecord
{
    protected static string $resource = SiidPublicacionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
