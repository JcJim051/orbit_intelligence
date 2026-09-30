<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\DependenciaCatalog;

class DependenciaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new DependenciaCatalog;
    }
}
