<?php

namespace App\Filament\Resources\MicrositioUltimoResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioUltimoResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioUltimos extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioUltimoResource::class;
}
