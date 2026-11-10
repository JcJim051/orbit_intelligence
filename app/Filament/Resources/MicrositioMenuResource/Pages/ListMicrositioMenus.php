<?php

namespace App\Filament\Resources\MicrositioMenuResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioMenuResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioMenus extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioMenuResource::class;
}
