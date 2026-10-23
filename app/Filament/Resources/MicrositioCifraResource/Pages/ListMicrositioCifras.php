<?php

namespace App\Filament\Resources\MicrositioCifraResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioCifraResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioCifras extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioCifraResource::class;
}
