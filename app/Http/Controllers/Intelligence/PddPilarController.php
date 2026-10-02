<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\PddPilarCatalog;

class PddPilarController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new PddPilarCatalog;
    }
}
