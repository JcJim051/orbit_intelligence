<?php

namespace App\Filament\Resources\MicrositioHeroResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioHeroResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioHeroes extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioHeroResource::class;
}
