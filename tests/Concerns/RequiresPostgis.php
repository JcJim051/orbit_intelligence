<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait RequiresPostgis
{
    protected function setUpRequiresPostgis(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Requiere PostgreSQL con PostGIS. Crear la base y ejecutar: php artisan test --configuration=phpunit.postgis.xml --compact'
            );
        }
    }
}
