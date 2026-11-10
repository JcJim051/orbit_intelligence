<?php

namespace App\Filament\Resources\MicrositioPoliticaResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioPoliticaResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioPoliticas extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioPoliticaResource::class;
}
