<?php

namespace App\Filament\Resources\MicrositioIndicadorResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioIndicadorResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioIndicadores extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioIndicadorResource::class;
}
