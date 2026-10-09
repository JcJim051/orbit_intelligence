<?php

namespace App\Contracts;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\Capa;

interface ConsultaCapa
{
    /**
     * Cruza una capa con el polígono de un análisis.
     * La descarga, el cruce espacial y el uso de caché quedan fuera de este corte.
     *
     * @return array{
     *     conteo: int,
     *     area_m2: float|null,
     *     longitud_m: float|null,
     *     resumen: array<string, mixed>,
     *     servido_desde: string,
     *     cita_fuente: string,
     *     url_fuente: string,
     *     licencia: string,
     *     fecha_corte: string,
     *     obsoleto: bool,
     *     capa_sincronizacion_id: int
     * }
     */
    public function consultar(AnalisisArea $analisis, Capa $capa): array;
}
