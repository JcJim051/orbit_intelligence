<?php

namespace App\Filament\Resources\MicrositioAliadoResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioAliadoResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioAliados extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioAliadoResource::class;
}
