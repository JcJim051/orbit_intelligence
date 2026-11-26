<?php

namespace App\Filament\Resources\MicrositioIndicadorResource\Pages;

use App\Filament\Micrositio\EncabezadoMicrositio;
use App\Filament\Resources\MicrositioIndicadorResource;
use Filament\Resources\Pages\ListRecords;

class ListMicrositioIndicadores extends ListRecords
{
    use EncabezadoMicrositio;

    protected static string $resource = MicrositioIndicadorResource::class;

    public function getSubheading(): ?string
    {
        return 'El indicador, el tema y el tablero vienen del catálogo del módulo Indicadores. Aquí solo se define su visibilidad y se publica la sección.';
    }
}
