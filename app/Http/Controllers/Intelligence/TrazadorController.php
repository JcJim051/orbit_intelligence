<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\TrazadorCatalog;

class TrazadorController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return app(TrazadorCatalog::class);
    }
}
