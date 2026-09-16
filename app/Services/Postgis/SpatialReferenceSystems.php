<?php

namespace App\Services\Postgis;

class SpatialReferenceSystems
{
    /** @return array<int, string> */
    public static function storageOptions(): array
    {
        return [
            9377 => 'MAGNA-SIRGAS / Origen Nacional (EPSG:9377)',
            4686 => 'MAGNA-SIRGAS geográficas (EPSG:4686)',
            3114 => 'MAGNA-SIRGAS / Colombia Far West zone (EPSG:3114)',
            3115 => 'MAGNA-SIRGAS / Colombia West zone (EPSG:3115)',
            3116 => 'MAGNA-SIRGAS / Colombia Bogota zone (EPSG:3116)',
            3117 => 'MAGNA-SIRGAS / Colombia East Central zone (EPSG:3117)',
            3118 => 'MAGNA-SIRGAS / Colombia East zone (EPSG:3118)',
            4326 => 'WGS 84 geográficas (EPSG:4326)',
        ];
    }
}
