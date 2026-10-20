<?php

namespace App\Services\InteligenciaGeografica;

use RuntimeException;

class Ogr2OgrNoDisponible extends RuntimeException
{
    /**
     * @param  list<string>  $rutas
     */
    public function __construct(public array $rutas)
    {
        parent::__construct('No está disponible ogr2ogr para generar el shapefile.');
    }
}
