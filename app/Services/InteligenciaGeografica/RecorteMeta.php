<?php

namespace App\Services\InteligenciaGeografica;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecorteMeta
{
    public const CODIGO = 'META';

    public const MENSAJE_FUERA = 'El polígono está por fuera del Meta. Las capas solo cubren el departamento, así que no hay área para analizar.';

    public const AREA_MINIMA_M2 = 1.0;

    /**
     * Mide la parte fuera del Meta y deja listas las geometrías en EPSG:9377.
     *
     * @return array{ha_fuera: float, pct_fuera: float, geojson_original: string, geojson_analizada: string}
     */
    public function medir(string $geoJson, int $srid): array
    {
        $fila = DB::selectOne(
            'with entrada as (
                select ST_MakeValid(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), ?), 9377)) as geom
             ),
             limite as (
                select geom from inteligencia.limite_referencia where codigo = ?
             ),
             corte as (
                select entrada.geom as original,
                       ST_Intersection(entrada.geom, limite.geom) as interseccion
                from entrada
                join limite on true
             )
             select ST_AsGeoJSON(ST_Multi(ST_CollectionExtract(original, 3))) as original,
                    ST_AsGeoJSON(ST_Multi(ST_CollectionExtract(ST_MakeValid(interseccion), 3))) as analizada,
                    ST_Area(original) as area_original,
                    coalesce(ST_Area(interseccion), 0) as area_dentro
             from corte',
            [$geoJson, $srid, self::CODIGO],
        );

        if ($fila === null || ! is_string($fila->original) || $fila->original === '') {
            throw ValidationException::withMessages([
                'geojson' => 'No está cargado el límite departamental del Meta (DANE MGN).',
            ]);
        }

        $areaOriginal = (float) $fila->area_original;
        $areaDentro = (float) $fila->area_dentro;

        if ($areaDentro <= self::AREA_MINIMA_M2 || ! is_string($fila->analizada) || $fila->analizada === '') {
            throw ValidationException::withMessages([
                'geojson' => self::MENSAJE_FUERA,
            ]);
        }

        $haFuera = max(0, round(($areaOriginal - $areaDentro) / 10000, 4));
        $pctFuera = $areaOriginal > 0
            ? max(0, round((($areaOriginal - $areaDentro) / $areaOriginal) * 100, 2))
            : 0.0;

        if ($pctFuera >= 100) {
            $pctFuera = 99.99;
        }

        return [
            'ha_fuera' => $haFuera,
            'pct_fuera' => $pctFuera,
            'geojson_original' => $fila->original,
            'geojson_analizada' => $fila->analizada,
        ];
    }

    /**
     * @return array{cita: string, url: string, fecha: string}|null
     */
    public function resumen(): ?array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return null;
        }

        $fila = DB::selectOne(
            'select cita_fuente, url_fuente, fecha_corte from inteligencia.limite_referencia where codigo = ?',
            [self::CODIGO],
        );

        if ($fila === null) {
            return null;
        }

        return [
            'cita' => $fila->cita_fuente,
            'url' => $fila->url_fuente,
            'fecha' => Carbon::parse($fila->fecha_corte)->timezone('America/Bogota')->format('d/m/Y'),
        ];
    }
}
