<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\MetaResultadoCatalog;

class MetaResultadoController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new MetaResultadoCatalog;
    }
}
