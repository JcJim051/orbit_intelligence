<?php

namespace App\Filament\Resources\MicrositioPublicacionResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioPublicacionResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioPublicaciones extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioPublicacionResource::class;
}
