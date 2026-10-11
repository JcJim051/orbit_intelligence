<?php

namespace App\Services\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InformeAnalisis
{
    /**
     * @return Collection<int, array{
     *     capa: string,
     *     pendiente: bool,
     *     conteo: int|null,
     *     area_m2: string|null,
     *     cita_fuente: string|null,
     *     url_fuente: string|null,
     *     licencia: string|null,
     *     fecha_corte: string|null,
     *     consulted_at: string|null,
     *     origen: string|null,
     *     obsoleto: bool,
     *     advertencia: string|null,
     *     detalles: list<array{identificador: string, atributos: string}>
     * }>
     */
    public function filas(AnalisisArea $analisis): Collection
    {
        $resultados = $analisis->resultados()->with('capa.fuente')->get()->keyBy('capa_id');

        return Capa::query()
            ->with('fuente')
            ->where('activa', true)
            ->orderBy('codigo')
            ->get()
            ->map(function (Capa $capa) use ($analisis, $resultados): array {
                /** @var AnalisisResultado|null $resultado */
                $resultado = $resultados->get($capa->id);

                if ($resultado === null) {
                    return [
                        'capa' => $capa->etiqueta(),
                        'pendiente' => true,
                        'conteo' => null,
                        'area_m2' => null,
                        'cita_fuente' => null,
                        'url_fuente' => null,
                        'licencia' => null,
                        'fecha_corte' => null,
                        'consulted_at' => null,
                        'origen' => null,
                        'obsoleto' => false,
                        'advertencia' => null,
                        'detalles' => [],
                    ];
                }

                return [
                    'capa' => $capa->etiqueta(),
                    'pendiente' => false,
                    'conteo' => $resultado->conteo,
                    'area_m2' => $resultado->area_m2,
                    'cita_fuente' => $resultado->cita_fuente,
                    'url_fuente' => $resultado->url_fuente,
                    'licencia' => $resultado->licencia,
                    'fecha_corte' => $resultado->fecha_corte?->format('d/m/Y H:i'),
                    'consulted_at' => $resultado->consulted_at?->format('d/m/Y H:i'),
                    'origen' => $resultado->servido_desde->label(),
                    'obsoleto' => $resultado->obsoleto,
                    'advertencia' => $resultado->resumen['advertencia'] ?? null,
                    'detalles' => $resultado->conteo > 0 ? $this->cruces($analisis, $capa) : [],
                ];
            });
    }

    /**
     * @return list<array{identificador: string, atributos: string}>
     */
    private function cruces(AnalisisArea $analisis, Capa $capa): array
    {
        $filas = DB::select(
            'select coalesce(cache.identificador_origen, \'sin identificador\') as identificador,
                    cache.atributos::text as atributos
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ? and ST_Intersects(cache.geom, area.geom)
             order by cache.identificador_origen
             limit 100',
            [$analisis->id, $capa->id],
        );

        return array_map(fn (object $fila): array => [
            'identificador' => (string) $fila->identificador,
            'atributos' => (string) $fila->atributos,
        ], $filas);
    }
}
