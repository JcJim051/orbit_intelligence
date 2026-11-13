<?php

namespace App\Filament\Resources\SiidSincronizacions\Pages;

use App\Filament\Resources\SiidSincronizacions\SiidSincronizacionResource;
use Filament\Resources\Pages\ListRecords;

class ListSiidSincronizacions extends ListRecords
{
    protected static string $resource = SiidSincronizacionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
