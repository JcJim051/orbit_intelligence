<?php

namespace App\Filament\Resources\MicrositioTextoInicioResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioTextoInicioResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioTextosInicio extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioTextoInicioResource::class;
}
