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
    /**
     * @var list<string>
     */
    public const COLORES = [
        '#2563eb', '#dc2626', '#059669', '#d97706', '#7c3aed', '#0891b2',
        '#be185d', '#4d7c0f', '#c2410c', '#0f766e', '#a21caf',
    ];

    public function filas(AnalisisArea $analisis): Collection
    {
        $resultados = $analisis->resultados()->with('capa.fuente')->get()->keyBy('capa_id');
        $areaEstudio = $analisis->areaHectareas();
        $capas = Capa::query()->with('fuente')->where('activa', true)->orderBy('codigo')->get();

        return $capas->values()->map(function (Capa $capa, int $indice) use ($analisis, $resultados, $areaEstudio): array {
            /** @var AnalisisResultado|null $resultado */
            $resultado = $resultados->get($capa->id);
            $base = [
                'id' => $capa->id,
                'capa' => $capa->etiqueta(),
                'color' => self::COLORES[$indice % count(self::COLORES)],
                'fuente_catalogo' => $capa->fuente?->etiqueta(),
            ];

            if ($resultado === null) {
                return [
                    ...$base,
                    'pendiente' => true,
                    'conteo' => null,
                    'area_m2' => null,
                    'area_ha' => null,
                    'porcentaje' => null,
                    'cita_fuente' => null,
                    'url_fuente' => null,
                    'licencia' => null,
                    'fecha_corte' => null,
                    'consulted_at' => null,
                    'origen' => null,
                    'servido_desde' => null,
                    'obsoleto' => false,
                    'vigencia' => null,
                    'advertencia' => null,
                    'detalles' => [],
                ];
            }

            $areaHa = is_numeric($resultado->area_m2) ? round(((float) $resultado->area_m2) / 10000, 2) : null;
            $porcentaje = ($areaHa !== null && $areaEstudio !== null && $areaEstudio > 0)
                ? round(min(100, ($areaHa / $areaEstudio) * 100), 1)
                : null;

            return [
                ...$base,
                'pendiente' => false,
                'conteo' => $resultado->conteo,
                'area_m2' => $resultado->area_m2,
                'area_ha' => $areaHa,
                'porcentaje' => $porcentaje,
                'cita_fuente' => $resultado->cita_fuente,
                'url_fuente' => $resultado->url_fuente,
                'licencia' => $resultado->licencia,
                'fecha_corte' => $resultado->fecha_corte?->format('d/m/Y H:i'),
                'consulted_at' => $resultado->consulted_at?->format('d/m/Y H:i'),
                'origen' => $resultado->servido_desde->label(),
                'servido_desde' => $resultado->servido_desde->value,
                'obsoleto' => $resultado->obsoleto,
                'vigencia' => $resultado->obsoleto ? 'Posiblemente desactualizada' : 'Vigente',
                'advertencia' => $resultado->resumen['advertencia'] ?? null,
                'detalles' => $resultado->conteo > 0 ? $this->cruces($analisis, $capa) : [],
            ];
        });
    }

    /**
     * @return list<array{id: int|string, etiqueta: string, color: string, geojson: array<string, mixed>}>
     */
    public function geometrias(AnalisisArea $analisis): array
    {
        $colores = $this->filas($analisis)->keyBy('id');

        return $analisis->resultados()->with('capa')->get()->map(function (AnalisisResultado $resultado) use ($analisis, $colores): array {
            $fila = $colores->get($resultado->capa_id);

            return [
                'id' => $resultado->capa_id,
                'etiqueta' => $resultado->capa?->etiqueta() ?? 'Capa',
                'color' => $fila['color'] ?? self::COLORES[0],
                'geojson' => $this->coleccion($analisis, $resultado->capa_id),
            ];
        })->all();
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

    /**
     * @return array{type: string, features: list<mixed>}
     */
    private function coleccion(AnalisisArea $analisis, int|string $capaId): array
    {
        $fila = DB::selectOne(
            "select coalesce(json_agg(feature), '[]'::json) as features
             from (
                select json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Intersection(cache.geom, area.geom), 4326))::json,
                    'properties', json_build_object(
                        'identificador', coalesce(cache.identificador_origen, 'sin identificador'),
                        'atributos', cache.atributos
                    )
                ) as feature
                from inteligencia.capa_objeto_cache cache
                join inteligencia.analisis_area area on area.id = ?
                where cache.capa_id = ? and ST_Intersects(cache.geom, area.geom)
                order by cache.identificador_origen
                limit 200
             ) cruces",
            [$analisis->id, $capaId],
        );

        $features = json_decode((string) ($fila->features ?? '[]'), true);

        return [
            'type' => 'FeatureCollection',
            'features' => is_array($features) ? $features : [],
        ];
    }
}
