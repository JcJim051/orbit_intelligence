<?php

namespace App\Services\InteligenciaGeografica;

use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;

class RegistrarSincronizacionPendiente
{
    public function registrar(Capa $capa): CapaSincronizacion
    {
        $ahora = now();

        return CapaSincronizacion::query()->create([
            'capa_id' => $capa->id,
            'iniciado_at' => $ahora,
            'finalizado_at' => $ahora,
            'fecha_corte_detectada' => $capa->fecha_corte_fuente,
            'cambio' => false,
            'entidades_agregadas' => 0,
            'entidades_actualizadas' => 0,
            'entidades_eliminadas' => 0,
            'estado' => EstadoSincronizacionCapa::Fallido,
            'mensaje_error' => 'Conector pendiente. No se consultó la fuente ni se descargaron entidades.',
        ]);
    }
}
