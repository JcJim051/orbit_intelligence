<?php

namespace App\Filament\Micrositio;

trait EncabezadoMicrositio
{
    protected function getHeaderActions(): array
    {
        return static::getResource()::accionesDePagina();
    }
}
