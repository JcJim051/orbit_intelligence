<?php

namespace App\Services\InteligenciaGeografica;

use App\Contracts\ConsultaCapa;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\Capa;
use RuntimeException;

class ConsultaCapaNoImplementada implements ConsultaCapa
{
    public function consultar(AnalisisArea $analisis, Capa $capa): array
    {
        throw new RuntimeException('La consulta de capas de inteligencia geográfica todavía no está implementada.');
    }
}
