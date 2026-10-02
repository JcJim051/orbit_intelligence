<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\DependenciaReglaPasivaCatalog;

class DependenciaReglaPasivaController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new DependenciaReglaPasivaCatalog;
    }
}
