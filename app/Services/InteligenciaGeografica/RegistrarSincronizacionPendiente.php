<?php

namespace App\Services\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;

class RegistrarSincronizacionPendiente
{
    public function __construct(private SincronizarCapa $sincronizar) {}

    public function registrar(Capa $capa): CapaSincronizacion
    {
        return $this->sincronizar->ejecutar($capa);
    }
}
