<?php

namespace App\Services\InteligenciaGeografica;

use App\Contracts\ConsultaCapa;
use App\Enums\EstadoSincronizacionCapa;
use App\Enums\ServidoDesde;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Services\InteligenciaGeografica\ArcGis\ClienteArcGis;
use App\Services\InteligenciaGeografica\Conectores\CatalogoCapasAbiertas;
use App\Services\InteligenciaGeografica\Conectores\DefinicionCapaAbierta;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cruza el polígono del análisis con la fuente en vivo.
 * Si no hay sincronización atribuible, no inventa la cita.
 */
class ConsultaCapaOficial implements ConsultaCapa
{
    public function __construct(
        private CatalogoCapasAbiertas $catalogo,
        private ClienteArcGis $cliente,
        private AlmacenCapa $almacen,
    ) {}

    public function consultar(AnalisisArea $analisis, Capa $capa): array
    {
        $definicion = $this->catalogo->definicion($capa->codigo);

        if ($definicion === null || ! $definicion->disponible) {
            throw new RuntimeException('La capa '.$capa->codigo.' no tiene una fuente pública consultable.');
        }

        $sincronizacion = $this->sincronizacionAtribuible($capa);

        if ($sincronizacion === null || $capa->fecha_corte_fuente === null) {
            throw new RuntimeException('No hay una sincronización con fecha de corte para citar la consulta de '.$capa->codigo.'.');
        }

        $extension = DB::selectOne(
            'select ST_XMin(g) as oeste, ST_YMin(g) as sur, ST_XMax(g) as este, ST_YMax(g) as norte
             from (select ST_Transform(geom, 4326) as g from inteligencia.analisis_area where id = ?) area',
            [$analisis->id],
        );

        if ($extension === null || ! is_numeric($extension->oeste)) {
            throw new RuntimeException('El polígono del análisis no tiene extensión.');
        }

        /** @var array{0: float, 1: float, 2: float, 3: float} $bbox */
        $bbox = [(float) $extension->oeste, (float) $extension->sur, (float) $extension->este, (float) $extension->norte];

        if ($definicion->capasPaquete !== []) {
            return $this->desdePaqueteSincronizado($analisis, $capa, $definicion, $sincronizacion);
        }

        $this->almacen->abrir();

        try {
            foreach ($definicion->endpoints as $endpoint) {
                $meta = $this->cliente->json($endpoint['url'].'?f=pjson');
                $where = $endpoint['bbox'] ? '1=1' : $endpoint['where'];

                foreach ($this->cliente->paginas($endpoint['url'], $where, $bbox, null, $this->cliente->campoOid($meta), $this->cliente->tamanoPagina($meta)) as $pagina) {
                    $this->almacen->agregar($this->normalizar($pagina, $endpoint['clave'], $endpoint['campo_id']), 4326);
                }
            }

            $cruce = DB::selectOne(
                'select count(*)::int as conteo,
                        coalesce(sum(ST_Area(ST_Intersection(s.geom, area.geom))), 0) as area_m2,
                        coalesce(sum(ST_Length(ST_Intersection(s.geom, area.geom))), 0) as longitud_m
                 from ig_staging s
                 join inteligencia.analisis_area area on area.id = ?
                 where ST_Intersects(s.geom, area.geom)',
                [$analisis->id],
            );
        } finally {
            $this->almacen->descartar();
        }

        return [
            'conteo' => (int) ($cruce->conteo ?? 0),
            'area_m2' => (float) ($cruce->area_m2 ?? 0),
            'longitud_m' => (float) ($cruce->longitud_m ?? 0),
            'resumen' => ['origen' => 'fuente directa'],
            'servido_desde' => ServidoDesde::FuenteDirecta->value,
            'cita_fuente' => $capa->cita_fuente,
            'url_fuente' => $capa->url_servicio ?: (string) $definicion->urlServicio,
            'licencia' => $capa->licencia ?: $definicion->licencia,
            'fecha_corte' => $capa->fecha_corte_fuente->toIso8601String(),
            'obsoleto' => false,
            'capa_sincronizacion_id' => $sincronizacion->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function desdePaqueteSincronizado(AnalisisArea $analisis, Capa $capa, DefinicionCapaAbierta $definicion, CapaSincronizacion $sincronizacion): array
    {
        $cruce = DB::selectOne(
            'select count(*)::int as conteo,
                    coalesce(sum(ST_Area(ST_Intersection(cache.geom, area.geom))), 0) as area_m2,
                    coalesce(sum(ST_Length(ST_Intersection(cache.geom, area.geom))), 0) as longitud_m
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ? and ST_Intersects(cache.geom, area.geom)',
            [$analisis->id, $capa->id],
        );

        return [
            'conteo' => (int) ($cruce->conteo ?? 0),
            'area_m2' => (float) ($cruce->area_m2 ?? 0),
            'longitud_m' => (float) ($cruce->longitud_m ?? 0),
            'resumen' => [
                'origen' => 'paquete catastral sincronizado',
                'nota' => 'Villavicencio (50001) no cubierto: gestor propio.',
            ],
            'servido_desde' => ServidoDesde::Copia->value,
            'cita_fuente' => $capa->cita_fuente,
            'url_fuente' => $capa->url_servicio ?: (string) $definicion->urlServicio,
            'licencia' => $capa->licencia ?: $definicion->licencia,
            'fecha_corte' => $capa->fecha_corte_fuente->toIso8601String(),
            'obsoleto' => $capa->copiaExcedeTtl(),
            'capa_sincronizacion_id' => $sincronizacion->id,
        ];
    }

    private function sincronizacionAtribuible(Capa $capa): ?CapaSincronizacion
    {
        return $capa->sincronizaciones()
            ->whereIn('estado', [EstadoSincronizacionCapa::Exitoso, EstadoSincronizacionCapa::SinCambios])
            ->latest('finalizado_at')
            ->first();
    }

    /**
     * @param  list<array<string, mixed>>  $entidades
     * @return list<array{id: string, atributos: array<string, mixed>, geom: string}>
     */
    private function normalizar(array $entidades, string $clave, string $campoId): array
    {
        $normalizadas = [];

        foreach ($entidades as $entidad) {
            $propiedades = is_array($entidad['properties'] ?? null) ? $entidad['properties'] : [];
            $geometria = $entidad['geometry'] ?? null;

            if (! is_array($geometria)) {
                continue;
            }

            $valor = null;

            foreach ($propiedades as $nombre => $candidato) {
                if (is_string($nombre) && strcasecmp($nombre, $campoId) === 0 && $candidato !== null && $candidato !== '') {
                    $valor = (string) $candidato;
                    break;
                }
            }

            $valor ??= isset($entidad['id']) ? (string) $entidad['id'] : null;

            if ($valor === null || $valor === '') {
                continue;
            }

            $id = $clave.':'.$valor;
            $normalizadas[] = [
                'id' => strlen($id) > 120 ? substr(hash('sha256', $id), 0, 40) : $id,
                'atributos' => ['_capa' => $clave],
                'geom' => json_encode($geometria, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];
        }

        return $normalizadas;
    }
}
