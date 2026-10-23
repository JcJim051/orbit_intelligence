<?php

namespace App\Filament\Resources\MicrositioPieResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioPieResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioPies extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioPieResource::class;
}
