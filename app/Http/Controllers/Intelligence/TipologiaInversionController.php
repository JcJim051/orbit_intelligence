<?php

namespace App\Http\Controllers\Intelligence;

use App\Services\Intelligence\Catalogs\CatalogDefinition;
use App\Services\Intelligence\Catalogs\TipologiaInversionCatalog;

class TipologiaInversionController extends CatalogController
{
    protected function catalog(): CatalogDefinition
    {
        return new TipologiaInversionCatalog;
    }
}
