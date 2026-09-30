<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\PddEjeCatalog;

class PddEjeController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new PddEjeCatalog;
    }
}
