<?php

namespace App\Services\Analitica;

use Illuminate\Support\Facades\DB;

class EsquemaAnalitica
{
    public static function tabla(string $nombre): string
    {
        return self::pgsql() ? 'analitica.'.$nombre : 'analitica_'.$nombre;
    }

    public static function pgsql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }
}
