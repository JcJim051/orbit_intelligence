<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\SectorMgaCatalog;

class SectorMgaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new SectorMgaCatalog;
    }
}
