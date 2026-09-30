<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\MunicipioCatalog;

class MunicipioController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new MunicipioCatalog;
    }
}
