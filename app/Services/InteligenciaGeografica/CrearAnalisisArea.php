<?php

namespace App\Services\InteligenciaGeografica;

use App\Enums\EstadoAnalisisArea;
use App\Enums\ModoAnalisis;
use App\Enums\OrigenGeometriaAnalisis;
use App\Enums\ServidoDesde;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearAnalisisArea
{
    public function __construct(private LectorGeometria $lector) {}

    public function crear(
        User $usuario,
        ModoAnalisis $modo,
        ?string $proyectoId,
        ?string $geoJsonDibujado,
        ?string $nombreArchivo,
        ?string $contenidoArchivo,
    ): AnalisisArea {
        [$geoJson, $origen, $nombre] = $this->geometria($geoJsonDibujado, $nombreArchivo, $contenidoArchivo);

        try {
            $analisis = AnalisisArea::crearConGeoJson([
                'investment_project_id' => $proyectoId,
                'user_id' => $usuario->id,
                'origen_geometria' => $origen,
                'nombre_archivo' => $nombre,
                'fecha' => now(),
                'estado' => EstadoAnalisisArea::Listo,
                'modo' => $modo,
            ], $geoJson);
        } catch (\Throwable $excepcion) {
            throw ValidationException::withMessages([
                'geojson' => 'No se pudo guardar el polígono. Revise que sea un área válida. '.$excepcion->getMessage(),
            ]);
        }

        $this->completarDesdeCache($analisis);

        return $analisis->refresh();
    }

    /**
     * @return array{0: string, 1: OrigenGeometriaAnalisis, 2: ?string}
     */
    private function geometria(?string $geoJsonDibujado, ?string $nombreArchivo, ?string $contenidoArchivo): array
    {
        if (is_string($contenidoArchivo) && $contenidoArchivo !== '' && is_string($nombreArchivo) && $nombreArchivo !== '') {
            try {
                $geoJson = $this->lector->desdeArchivo($nombreArchivo, $contenidoArchivo);
            } catch (\InvalidArgumentException $excepcion) {
                throw ValidationException::withMessages(['archivo' => $excepcion->getMessage()]);
            }

            $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
            $origen = match ($extension) {
                'kml' => OrigenGeometriaAnalisis::Kml,
                'geojson', 'json' => OrigenGeometriaAnalisis::GeoJson,
                default => OrigenGeometriaAnalisis::Kmz,
            };

            return [$geoJson, $origen, $nombreArchivo];
        }

        if (is_string($geoJsonDibujado) && trim($geoJsonDibujado) !== '') {
            try {
                return [$this->lector->desdeGeoJson($geoJsonDibujado), OrigenGeometriaAnalisis::Dibujo, null];
            } catch (\InvalidArgumentException $excepcion) {
                throw ValidationException::withMessages(['geojson' => $excepcion->getMessage()]);
            }
        }

        throw ValidationException::withMessages([
            'geojson' => 'Dibuje un polígono o cargue un archivo KMZ, KML o GeoJSON.',
        ]);
    }

    private function completarDesdeCache(AnalisisArea $analisis): void
    {
        Capa::query()->where('activa', true)->orderBy('codigo')->each(function (Capa $capa) use ($analisis): void {
            $this->resultadoDesdeCache($analisis, $capa);
        });
    }

    private function resultadoDesdeCache(AnalisisArea $analisis, Capa $capa): void
    {
        $tieneCache = DB::table('inteligencia.capa_objeto_cache')->where('capa_id', $capa->id)->exists();

        if (! $tieneCache || $capa->fecha_corte_fuente === null || $capa->cita_fuente === '') {
            return;
        }

        $sincronizacion = $capa->sincronizaciones()
            ->whereIn('estado', ['exitoso', 'sin_cambios'])
            ->latest('finalizado_at')
            ->first();

        if ($sincronizacion === null) {
            return;
        }

        $cruce = DB::selectOne(
            'select count(*)::int as conteo,
                    coalesce(sum(ST_Area(ST_Intersection(cache.geom, area.geom))), 0) as area_m2,
                    coalesce(sum(ST_Length(ST_Intersection(cache.geom, area.geom))), 0) as longitud_m
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ? and ST_Intersects(cache.geom, area.geom)',
            [$analisis->id, $capa->id],
        );

        $usaCopiaPorFalla = $analisis->esOficial();

        AnalisisResultado::query()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => $capa->id,
            'conteo' => (int) $cruce->conteo,
            'area_m2' => $cruce->area_m2,
            'longitud_m' => $cruce->longitud_m,
            'resumen' => $usaCopiaPorFalla
                ? ['advertencia' => 'No se consultó la fuente. Se usó la copia local.']
                : ['origen' => 'copia local'],
            'consulted_at' => now(),
            'servido_desde' => $usaCopiaPorFalla ? ServidoDesde::CopiaPorFallaFuente : ServidoDesde::Copia,
            'cita_fuente' => $capa->cita_fuente,
            'url_fuente' => $capa->url_servicio ?: 'URL pendiente de confirmación',
            'licencia' => $capa->licencia ?: 'Licencia pendiente de confirmación',
            'fecha_corte' => $capa->fecha_corte_fuente,
            'obsoleto' => $usaCopiaPorFalla || $capa->copiaExcedeTtl(),
            'capa_sincronizacion_id' => $sincronizacion->id,
        ]);
    }
}
