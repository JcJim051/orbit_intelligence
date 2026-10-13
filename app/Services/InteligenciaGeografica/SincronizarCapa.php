<?php

namespace App\Services\InteligenciaGeografica;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Services\InteligenciaGeografica\ArcGis\ClienteArcGis;
use App\Services\InteligenciaGeografica\Conectores\CatalogoCapasAbiertas;
use App\Services\InteligenciaGeografica\Conectores\DefinicionCapaAbierta;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Descarga una capa abierta, la reproyecta a EPSG:9377 y deja la bitácora de la corrida.
 */
class SincronizarCapa
{
    public function __construct(
        private CatalogoCapasAbiertas $catalogo,
        private ClienteArcGis $cliente,
        private AlmacenCapa $almacen,
    ) {}

    public function ejecutar(Capa $capa): CapaSincronizacion
    {
        $definicion = $this->catalogo->definicion($capa->codigo);

        if ($definicion === null) {
            throw new InvalidArgumentException($capa->codigo.' no tiene conector externo. Los estudios de suelos son cargue propio.');
        }

        set_time_limit(0);
        $this->cerrarCorridasAbiertas($capa);

        $sincronizacion = CapaSincronizacion::query()->create([
            'capa_id' => $capa->id,
            'iniciado_at' => now(),
            'cambio' => false,
            'entidades_agregadas' => 0,
            'entidades_actualizadas' => 0,
            'entidades_eliminadas' => 0,
            'estado' => EstadoSincronizacionCapa::EnProceso,
            'url_consultada' => $definicion->urlServicio,
            'licencia_detectada' => $definicion->licencia,
        ]);

        try {
            if (! $definicion->disponible) {
                return $this->cerrar($sincronizacion, $capa, [
                    'estado' => EstadoSincronizacionCapa::FuenteNoDisponible,
                    'mensaje_error' => (string) $definicion->motivoNoDisponible,
                    'nota' => 'Hace falta la fuente indicada en el mensaje. Un administrador puede cargar un shapefile o GeoJSON con cita y fecha de corte.',
                    'frescura' => EstadoFrescuraCapa::Error,
                ]);
            }

            return $this->descargar($sincronizacion, $capa, $definicion);
        } catch (Throwable $excepcion) {
            if ($this->fuenteNoRespondio($excepcion)) {
                return $this->cerrar($sincronizacion, $capa, [
                    'estado' => EstadoSincronizacionCapa::FuenteNoDisponible,
                    'mensaje_error' => $excepcion->getMessage(),
                    'nota' => 'La fuente no respondió tras 2 intentos. Un administrador puede cargar un shapefile o GeoJSON con cita y fecha de corte.',
                    'frescura' => EstadoFrescuraCapa::Error,
                ]);
            }

            return $this->cerrar($sincronizacion, $capa, [
                'estado' => EstadoSincronizacionCapa::Fallido,
                'mensaje_error' => $excepcion->getMessage(),
                'frescura' => EstadoFrescuraCapa::Error,
            ]);
        } finally {
            $this->almacen->descartar();
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function descargar(CapaSincronizacion $sincronizacion, Capa $capa, DefinicionCapaAbierta $definicion): CapaSincronizacion
    {
        $metadatos = [];

        foreach ($definicion->endpoints as $endpoint) {
            $metadatos[] = $this->cliente->json($endpoint['url'].'?f=pjson');
        }

        $fecha = $this->fechaDeCorte($definicion, $metadatos);
        $licencia = $this->licenciaDetectada($definicion);
        $anterior = $this->anterior($capa);

        if ($fecha !== null && ! $capa->requiereDescarga($fecha)) {
            return $this->cerrar($sincronizacion, $capa, [
                'estado' => EstadoSincronizacionCapa::SinCambios,
                'fecha_corte' => $fecha,
                'checksum' => $anterior?->checksum,
                'licencia' => $licencia,
                'frescura' => EstadoFrescuraCapa::Vigente,
                'cita' => $definicion->cita,
            ]);
        }

        $this->almacen->abrir();
        $recibidas = 0;
        $notas = [];

        foreach ($definicion->endpoints as $indice => $endpoint) {
            $meta = $metadatos[$indice];
            $orden = $this->cliente->campoOid($meta);
            $tamano = $this->cliente->tamanoPagina($meta);
            $bbox = $endpoint['bbox'] ? $definicion->bbox : null;

            foreach ($this->cliente->paginas($endpoint['url'], $endpoint['where'], $bbox, null, $orden, $tamano, $this->cliente->admitePaginacion($meta)) as $pagina) {
                $recibidas += count($pagina);
                $this->almacen->agregar($this->normalizar($pagina, $endpoint['clave'], $endpoint['campo_id']), 4326);
            }
        }

        if ($recibidas > 0 && $this->almacen->total() === 0) {
            throw new \RuntimeException('Ninguna geometría de la fuente pudo reproyectarse a EPSG:9377.');
        }

        if ($definicion->recorteUrl !== null) {
            try {
                $recortadas = $this->recortar($definicion->recorteUrl);
                $notas[] = 'Se descartaron '.$recortadas.' entidades fuera del límite departamental del Meta.';
            } catch (Throwable $excepcion) {
                $notas[] = 'No se pudo recortar con el límite departamental ('.$excepcion->getMessage().'). Se conservó la envolvente.';
            }
        }

        if ($fecha === null) {
            $notas[] = 'La fuente no publica fecha de corte en los metadatos del servicio. La copia queda guardada, pero el análisis no la usa hasta que esa fecha exista.';
        }

        $checksum = $this->almacen->checksum();

        if ($anterior?->checksum !== null && hash_equals($anterior->checksum, $checksum)) {
            return $this->cerrar($sincronizacion, $capa, [
                'estado' => EstadoSincronizacionCapa::SinCambios,
                'fecha_corte' => $fecha,
                'checksum' => $checksum,
                'licencia' => $licencia,
                'nota' => $this->nota($notas),
                'frescura' => $fecha === null ? EstadoFrescuraCapa::PosiblementeDesactualizada : EstadoFrescuraCapa::Vigente,
                'cita' => $definicion->cita,
            ]);
        }

        $conteos = $this->almacen->fusionar($capa->id);
        $cambio = ($conteos['agregadas'] + $conteos['actualizadas'] + $conteos['eliminadas']) > 0;

        return $this->cerrar($sincronizacion, $capa, [
            'estado' => $cambio ? EstadoSincronizacionCapa::Exitoso : EstadoSincronizacionCapa::SinCambios,
            'fecha_corte' => $fecha,
            'checksum' => $checksum,
            'licencia' => $licencia,
            'nota' => $this->nota($notas),
            'cambio' => $cambio,
            'conteos' => $conteos,
            'frescura' => $fecha === null ? EstadoFrescuraCapa::PosiblementeDesactualizada : EstadoFrescuraCapa::Vigente,
            'cita' => $definicion->cita,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $metadatos
     */
    private function fechaDeCorte(DefinicionCapaAbierta $definicion, array $metadatos): ?Carbon
    {
        $fechas = [];

        foreach ($metadatos as $meta) {
            $edicion = $this->cliente->edicion($meta);

            if ($edicion !== null) {
                $fechas[] = $edicion;
            }
        }

        if ($fechas !== []) {
            return collect($fechas)->sortBy(fn (Carbon $fecha): int => $fecha->getTimestamp())->last();
        }

        if (is_string($definicion->itemUrl) && $definicion->itemUrl !== '') {
            $item = $this->cliente->json($definicion->itemUrl);
            $modificado = $item['modified'] ?? null;

            if (is_numeric($modificado) && (int) $modificado > 0) {
                return Carbon::createFromTimestampMs((int) $modificado)->utc();
            }
        }

        if ($definicion->fechaEnDescripcion && is_string($definicion->descripcionUrl)) {
            $servicio = $this->cliente->json($definicion->descripcionUrl);
            $texto = trim(((string) ($servicio['serviceDescription'] ?? '')).' '.((string) ($servicio['description'] ?? '')));

            if (preg_match('/actualizado el (\d{2})\/(\d{2})\/(\d{4})/i', $texto, $coincidencia) === 1) {
                return Carbon::createFromFormat(
                    'd/m/Y',
                    $coincidencia[1].'/'.$coincidencia[2].'/'.$coincidencia[3],
                    'America/Bogota',
                )->startOfDay()->utc();
            }
        }

        if ($definicion->fechaEnZipDeLaDescripcion) {
            $descripcion = (string) ($metadatos[0]['description'] ?? '');

            if (preg_match('#https://\S+\.zip#', $descripcion, $coincidencia) === 1) {
                return $this->cliente->ultimaModificacion(rtrim($coincidencia[0], '.,);'));
            }
        }

        return null;
    }

    private function licenciaDetectada(DefinicionCapaAbierta $definicion): string
    {
        if (! is_string($definicion->itemUrl) || $definicion->itemUrl === '') {
            return $definicion->licencia;
        }

        try {
            $item = $this->cliente->json($definicion->itemUrl);
        } catch (Throwable) {
            return $definicion->licencia;
        }

        $texto = trim(html_entity_decode(strip_tags((string) ($item['licenseInfo'] ?? ''))));

        return $texto !== '' ? $texto : $definicion->licencia;
    }

    private function recortar(string $url): int
    {
        $meta = $this->cliente->json($url.'?f=pjson');
        $orden = $this->cliente->campoOid($meta);
        $tamano = $this->cliente->tamanoPagina($meta);
        $cargo = false;

        foreach ($this->cliente->paginas($url, '1=1', null, null, $orden, $tamano) as $pagina) {
            foreach ($pagina as $entidad) {
                $geometria = $entidad['geometry'] ?? null;

                if (! is_array($geometria)) {
                    continue;
                }

                $cargo = $this->almacen->agregarLimite(
                    json_encode($geometria, JSON_THROW_ON_ERROR),
                    4326,
                ) || $cargo;
            }
        }

        if (! $cargo) {
            throw new \RuntimeException('El límite departamental no trajo geometría.');
        }

        return $this->almacen->aplicarRecorte();
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

            $identificador = $this->identificador($propiedades, $campoId, $entidad['id'] ?? null);

            if ($identificador === null) {
                continue;
            }

            $atributos = [];

            foreach ($propiedades as $nombre => $valor) {
                if (! is_string($nombre) || str_starts_with(strtolower($nombre), 'shape')) {
                    continue;
                }

                $atributos[$nombre] = is_scalar($valor) || $valor === null ? $valor : json_encode($valor);
            }

            $atributos['_capa'] = $clave;
            $id = $clave.':'.$identificador;
            $normalizadas[] = [
                'id' => strlen($id) > 120 ? $clave.':'.substr(hash('sha256', $identificador), 0, 40) : $id,
                'atributos' => $atributos,
                'geom' => json_encode($geometria, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];
        }

        return $normalizadas;
    }

    /**
     * @param  array<string, mixed>  $propiedades
     */
    private function identificador(array $propiedades, string $campoId, mixed $idExplicito): ?string
    {
        foreach ($propiedades as $nombre => $valor) {
            if (is_string($nombre) && strcasecmp($nombre, $campoId) === 0 && $valor !== null && $valor !== '') {
                return (string) $valor;
            }
        }

        foreach (['OBJECTID', 'objectid', 'FID', 'GlobalID'] as $alternativa) {
            if (isset($propiedades[$alternativa]) && $propiedades[$alternativa] !== '') {
                return (string) $propiedades[$alternativa];
            }
        }

        if (is_scalar($idExplicito) && (string) $idExplicito !== '') {
            return (string) $idExplicito;
        }

        return null;
    }

    private function anterior(Capa $capa): ?CapaSincronizacion
    {
        return $capa->sincronizaciones()
            ->whereIn('estado', [EstadoSincronizacionCapa::Exitoso, EstadoSincronizacionCapa::SinCambios])
            ->latest('finalizado_at')
            ->first();
    }

    /**
     * @param  array{
     *     estado: EstadoSincronizacionCapa,
     *     mensaje_error?: string|null,
     *     nota?: string|null,
     *     fecha_corte?: Carbon|null,
     *     checksum?: string|null,
     *     licencia?: string|null,
     *     cambio?: bool,
     *     conteos?: array{agregadas: int, actualizadas: int, eliminadas: int},
     *     frescura?: EstadoFrescuraCapa,
     *     cita?: string|null
     * }  $cierre
     */
    private function cerrar(CapaSincronizacion $sincronizacion, Capa $capa, array $cierre): CapaSincronizacion
    {
        $conteos = $cierre['conteos'] ?? ['agregadas' => 0, 'actualizadas' => 0, 'eliminadas' => 0];
        $cambio = (bool) ($cierre['cambio'] ?? false);
        $estado = $cierre['estado'];

        if ($estado === EstadoSincronizacionCapa::SinCambios || $estado === EstadoSincronizacionCapa::FuenteNoDisponible) {
            $cambio = false;
            $conteos = ['agregadas' => 0, 'actualizadas' => 0, 'eliminadas' => 0];
        }

        $sincronizacion->fill([
            'finalizado_at' => now(),
            'fecha_corte_detectada' => $cierre['fecha_corte'] ?? null,
            'cambio' => $cambio,
            'entidades_agregadas' => $conteos['agregadas'],
            'entidades_actualizadas' => $conteos['actualizadas'],
            'entidades_eliminadas' => $conteos['eliminadas'],
            'estado' => $estado,
            'mensaje_error' => $cierre['mensaje_error'] ?? null,
            'licencia_detectada' => $cierre['licencia'] ?? $sincronizacion->licencia_detectada,
            'checksum' => $cierre['checksum'] ?? $sincronizacion->checksum,
            'nota' => $cierre['nota'] ?? null,
        ])->save();

        $atributos = [
            'estado_frescura' => $cierre['frescura'] ?? $capa->estado_frescura,
        ];

        if ($estado !== EstadoSincronizacionCapa::Fallido) {
            $atributos['fecha_ultima_sincronizacion'] = $sincronizacion->finalizado_at;
        }

        if (isset($cierre['cita']) && is_string($cierre['cita']) && $cierre['cita'] !== '') {
            $atributos['cita_fuente'] = $cierre['cita'];
        }

        if (isset($cierre['licencia']) && is_string($cierre['licencia']) && $cierre['licencia'] !== '') {
            $atributos['licencia'] = $cierre['licencia'];
        }

        if (array_key_exists('fecha_corte', $cierre) && $cierre['fecha_corte'] instanceof Carbon) {
            $atributos['fecha_corte_fuente'] = $cierre['fecha_corte'];
        }

        if ($estado === EstadoSincronizacionCapa::Fallido) {
            unset($atributos['fecha_corte_fuente'], $atributos['cita_fuente'], $atributos['licencia']);
        }

        $capa->fill($atributos)->save();

        return $sincronizacion->refresh();
    }

    private function fuenteNoRespondio(Throwable $excepcion): bool
    {
        if ($excepcion instanceof ConnectionException) {
            return true;
        }

        $mensaje = $excepcion->getMessage();

        foreach ([
            'tiempo de espera agotado',
            'Operation timed out',
            'cURL error 28',
            'Could not access any server machines',
            'La fuente no respondió',
        ] as $marca) {
            if (str_contains($mensaje, $marca)) {
                return true;
            }
        }

        return false;
    }

    private function cerrarCorridasAbiertas(Capa $capa): void
    {
        CapaSincronizacion::query()
            ->where('capa_id', $capa->id)
            ->where('estado', EstadoSincronizacionCapa::EnProceso)
            ->update([
                'estado' => EstadoSincronizacionCapa::Fallido,
                'finalizado_at' => now(),
                'mensaje_error' => 'La corrida anterior no terminó.',
                'cambio' => false,
                'entidades_agregadas' => 0,
                'entidades_actualizadas' => 0,
                'entidades_eliminadas' => 0,
            ]);
    }

    /**
     * @param  list<string>  $notas
     */
    private function nota(array $notas): ?string
    {
        $texto = trim(implode(' ', array_filter($notas)));

        if ($texto === '') {
            return null;
        }

        return mb_substr($texto, 0, 2000);
    }
}
