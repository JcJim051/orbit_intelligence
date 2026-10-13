<?php

namespace App\Services\InteligenciaGeografica;

use App\Contracts\ConsultaCapa;
use App\Enums\EstadoAnalisisArea;
use App\Enums\EstadoSincronizacionCapa;
use App\Enums\ModoAnalisis;
use App\Enums\OrigenGeometriaAnalisis;
use App\Enums\ServidoDesde;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CrearAnalisisArea
{
    public function __construct(
        private LectorGeometria $lector,
        private ConsultaCapa $consulta,
    ) {}

    public function crear(
        User $usuario,
        ModoAnalisis $modo,
        ?string $proyectoId,
        ?string $geoJsonDibujado,
        ?string $nombreArchivo,
        ?string $contenidoArchivo,
        int $sridOrigen = 4326,
        ?OrigenGeometriaAnalisis $origenDeclarado = null,
    ): AnalisisArea {
        [$geoJson, $origen, $nombre, $srid] = $this->geometria($geoJsonDibujado, $nombreArchivo, $contenidoArchivo, $sridOrigen, $origenDeclarado);

        try {
            $analisis = AnalisisArea::crearConGeoJson([
                'investment_project_id' => $proyectoId,
                'user_id' => $usuario->id,
                'origen_geometria' => $origen,
                'nombre_archivo' => $nombre,
                'fecha' => now(),
                'estado' => EstadoAnalisisArea::Listo,
                'modo' => $modo,
            ], $geoJson, $srid);
        } catch (Throwable $excepcion) {
            throw ValidationException::withMessages([
                'geojson' => 'No se pudo guardar el polígono. Revise que sea un área válida. '.$excepcion->getMessage(),
            ]);
        }

        $this->completarDesdeCache($analisis);

        return $analisis->refresh();
    }

    /**
     * @return array{0: string, 1: OrigenGeometriaAnalisis, 2: ?string, 3: int}
     */
    private function geometria(
        ?string $geoJsonDibujado,
        ?string $nombreArchivo,
        ?string $contenidoArchivo,
        int $sridOrigen,
        ?OrigenGeometriaAnalisis $origenDeclarado,
    ): array {
        if (is_string($contenidoArchivo) && $contenidoArchivo !== '' && is_string($nombreArchivo) && $nombreArchivo !== '') {
            try {
                $geoJson = $this->lector->desdeArchivo($nombreArchivo, $contenidoArchivo);
                $this->lector->afirmarCoordenadas($geoJson, 4326);
            } catch (\InvalidArgumentException $excepcion) {
                throw ValidationException::withMessages(['archivo' => $excepcion->getMessage()]);
            }

            $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
            $origen = match ($extension) {
                'kml' => OrigenGeometriaAnalisis::Kml,
                'geojson', 'json' => OrigenGeometriaAnalisis::GeoJson,
                'zip' => OrigenGeometriaAnalisis::Shapefile,
                default => OrigenGeometriaAnalisis::Kmz,
            };

            return [$geoJson, $origen, $nombreArchivo, 4326];
        }

        if (is_string($geoJsonDibujado) && trim($geoJsonDibujado) !== '') {
            if (strlen($geoJsonDibujado) > 2_000_000) {
                throw ValidationException::withMessages(['geojson' => 'El polígono supera el tamaño admitido.']);
            }

            try {
                $geoJson = $this->lector->desdeGeoJson($geoJsonDibujado);
                $this->lector->afirmarCoordenadas($geoJson, $sridOrigen);
            } catch (\InvalidArgumentException $excepcion) {
                throw ValidationException::withMessages(['geojson' => $excepcion->getMessage()]);
            }

            return [$geoJson, $origenDeclarado ?? OrigenGeometriaAnalisis::Dibujo, $nombreArchivo, $sridOrigen];
        }

        throw ValidationException::withMessages([
            'geojson' => 'Dibuje un polígono o cargue un KMZ, KML, GeoJSON o shapefile .zip.',
        ]);
    }

    private function completarDesdeCache(AnalisisArea $analisis): void
    {
        Capa::query()->where('activa', true)->orderBy('codigo')->each(function (Capa $capa) use ($analisis): void {
            if ($analisis->esOficial() && $this->resultadoDesdeFuente($analisis, $capa)) {
                return;
            }

            $this->resultadoDesdeCache($analisis, $capa);
        });
    }

    private function resultadoDesdeFuente(AnalisisArea $analisis, Capa $capa): bool
    {
        try {
            $consulta = $this->consulta->consultar($analisis, $capa);
        } catch (Throwable) {
            return false;
        }

        AnalisisResultado::query()->create([
            'analisis_area_id' => $analisis->id,
            'capa_id' => $capa->id,
            ...$consulta,
        ]);

        return true;
    }

    private function resultadoDesdeCache(AnalisisArea $analisis, Capa $capa): void
    {
        $version = $capa->versionVigente;

        if ($version === null || $capa->fecha_corte_fuente === null || $capa->cita_fuente === '') {
            return;
        }

        $sincronizacion = $capa->sincronizaciones()
            ->whereIn('estado', EstadoSincronizacionCapa::atribuibles())
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
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
               and ST_Intersects(cache.geom, area.geom)',
            [$analisis->id, $capa->id, $version->numero, $version->numero],
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
            'capa_version_id' => $version->id,
        ]);
    }
}
