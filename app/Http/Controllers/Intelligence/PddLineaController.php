<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\PddLineaCatalog;

class PddLineaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new PddLineaCatalog;
    }
}
