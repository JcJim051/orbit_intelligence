<?php

namespace App\Filament\Resources\MicrositioTemaResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioTemaResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioTemas extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioTemaResource::class;
}
