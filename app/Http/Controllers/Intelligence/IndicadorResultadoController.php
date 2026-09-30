<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\IndicadorResultadoCatalog;

class IndicadorResultadoController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new IndicadorResultadoCatalog;
    }
}
