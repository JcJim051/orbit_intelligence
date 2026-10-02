<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\PddProgramaCatalog;

class PddProgramaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new PddProgramaCatalog;
    }
}
