<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\PoliticaPublicaDepartamentalCatalog;

class PoliticaPublicaDepartamentalController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return app(PoliticaPublicaDepartamentalCatalog::class);
    }
}
