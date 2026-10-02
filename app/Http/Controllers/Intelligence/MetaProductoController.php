<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\MetaProductoCatalog;

class MetaProductoController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new MetaProductoCatalog;
    }
}
