<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\TrazadorCategoriaCatalog;

class TrazadorCategoriaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return app(TrazadorCategoriaCatalog::class);
    }
}
