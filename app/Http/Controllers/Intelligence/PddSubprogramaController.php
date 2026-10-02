<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\PddSubprogramaCatalog;

class PddSubprogramaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new PddSubprogramaCatalog;
    }
}
