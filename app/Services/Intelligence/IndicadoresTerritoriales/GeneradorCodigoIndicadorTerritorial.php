<?php

namespace App\Services\Intelligence\IndicadoresTerritoriales;

use App\Models\IndicadorTerritorial;
use Illuminate\Support\Facades\DB;

class GeneradorCodigoIndicadorTerritorial
{
    public function siguiente(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT pg_advisory_xact_lock(hashtext('indicadores-territoriales-codigo'))");
        }

        $ultimo = IndicadorTerritorial::query()
            ->where('codigo', 'like', 'IT-%')
            ->pluck('codigo')
            ->reduce(function (int $maximo, string $codigo): int {
                if (preg_match('/^IT-(\d+)$/', $codigo, $coincidencias) !== 1) {
                    return $maximo;
                }

                return max($maximo, (int) $coincidencias[1]);
            }, 0);

        return 'IT-'.str_pad((string) ($ultimo + 1), 4, '0', STR_PAD_LEFT);
    }
}
