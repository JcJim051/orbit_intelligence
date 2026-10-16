<?php

namespace App\Services\InteligenciaGeografica\ArcGis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Consulta metadatos y entidades de un FeatureServer o MapServer de ArcGIS.
 */
class ClienteArcGis
{
    /**
     * Sin paginación cada polígono se pide aparte: uno solo ya puede pesar varios megabytes.
     */
    private const LOTE_SIN_PAGINACION = 1;

    /**
     * Entidades decodificadas que se entregan juntas, sin acumular la página completa.
     */
    private const LOTE_MEMORIA = 10;

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $jsonPorUrl = [];

    public function __construct(private LectorGeoJsonFlujo $lector) {}

    /**
     * @return array<string, mixed>
     */
    public function json(string $url): array
    {
        if (isset($this->jsonPorUrl[$url])) {
            return $this->jsonPorUrl[$url];
        }

        $respuesta = $this->http()->get($url);
        $datos = $this->decodificar($respuesta, $url);
        $this->jsonPorUrl[$url] = $datos;

        return $datos;
    }

    /**
     * @return array{etag: ?string, length: ?string, modified: ?string}
     */
    public function cabeceras(string $url): array
    {
        try {
            $respuesta = $this->http()->head($url);

            if (! $respuesta->successful()) {
                return ['etag' => null, 'length' => null, 'modified' => null];
            }

            return [
                'etag' => $this->cabecera($respuesta, 'ETag'),
                'length' => $this->cabecera($respuesta, 'Content-Length'),
                'modified' => $this->cabecera($respuesta, 'Last-Modified'),
            ];
        } catch (Throwable) {
            return ['etag' => null, 'length' => null, 'modified' => null];
        }
    }

    public function ultimaModificacion(string $url): ?Carbon
    {
        $respuesta = $this->http()->head($url);
        $respuesta->throw();
        $cabecera = $respuesta->header('Last-Modified');

        if (! is_string($cabecera) || $cabecera === '') {
            return null;
        }

        return Carbon::parse($cabecera)->utc();
    }

    private function cabecera(Response $respuesta, string $nombre): ?string
    {
        $valor = $respuesta->header($nombre);

        if (! is_string($valor) || $valor === '') {
            return null;
        }

        return $valor;
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     * @param  array<string, mixed>|null  $poligono
     * @return \Generator<int, list<array<string, mixed>>>
     */
    public function paginas(string $url, string $where, ?array $bbox, ?array $poligono, ?string $orden, int $tamano, bool $paginacion = true): \Generator
    {
        if (! $paginacion) {
            yield from $this->paginasPorIdentificadores($url, $where, $bbox, $poligono, $tamano);

            return;
        }

        $desplazamiento = 0;
        $primeroAnterior = null;
        $pagina = 0;

        do {
            $parametros = [
                'where' => $where,
                'outFields' => '*',
                'returnGeometry' => 'true',
                'f' => 'geojson',
                'outSR' => '4326',
                'resultOffset' => $desplazamiento,
                'resultRecordCount' => $tamano,
            ];

            if (is_string($orden) && $orden !== '') {
                $parametros['orderByFields'] = $orden;
            }

            if ($poligono !== null) {
                $parametros['geometry'] = json_encode($poligono, JSON_THROW_ON_ERROR);
                $parametros['geometryType'] = 'esriGeometryPolygon';
                $parametros['inSR'] = '4326';
                $parametros['spatialRel'] = 'esriSpatialRelIntersects';
            } elseif ($bbox !== null) {
                $parametros['geometry'] = implode(',', $bbox);
                $parametros['geometryType'] = 'esriGeometryEnvelope';
                $parametros['inSR'] = '4326';
                $parametros['spatialRel'] = 'esriSpatialRelIntersects';
            }

            $archivo = $this->guardarConsulta($url.'/query', $parametros);

            try {
                $this->afirmarRespuesta($archivo, $url.'/query');
                $cantidad = 0;
                $primero = null;
                $lote = [];

                foreach ($this->lector->features($archivo) as $entidad) {
                    if ($cantidad === 0) {
                        $primero = $this->primerIdentificador([$entidad]);
                    }

                    $cantidad++;
                    $lote[] = $entidad;

                    if (count($lote) >= self::LOTE_MEMORIA) {
                        yield $lote;
                        $lote = [];
                    }
                }

                if ($lote !== []) {
                    yield $lote;
                }

                if ($cantidad === 0) {
                    $this->decodificarArchivo($archivo, $url.'/query');
                }

                $supera = $this->lector->excedio($archivo);
            } finally {
                @unlink($archivo);
            }

            if ($primero !== null && $primero === $primeroAnterior) {
                throw new RuntimeException('La fuente repitió la misma página. No se puede paginar '.$url.'.');
            }

            $primeroAnterior = $primero;
            $desplazamiento += $tamano;
            $pagina++;
            $sigue = $cantidad > 0 && ($supera || $cantidad >= $tamano) && $pagina < 5000;
        } while ($sigue);
    }

    /**
     * @param  array<string, mixed>  $metadatos
     */
    public function campoOid(array $metadatos): ?string
    {
        $campos = $metadatos['fields'] ?? [];

        if (! is_array($campos)) {
            return null;
        }

        foreach ($campos as $campo) {
            if (is_array($campo) && ($campo['type'] ?? null) === 'esriFieldTypeOID' && is_string($campo['name'] ?? null)) {
                return $campo['name'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $metadatos
     */
    public function admitePaginacion(array $metadatos): bool
    {
        $capacidad = $metadatos['advancedQueryCapabilities']['supportsPagination']
            ?? $metadatos['supportsPagination']
            ?? true;

        return (bool) $capacidad;
    }

    /**
     * @param  array<string, mixed>  $metadatos
     */
    public function tamanoPagina(array $metadatos): int
    {
        $maximo = $metadatos['maxRecordCount'] ?? 1000;
        $maximo = is_numeric($maximo) ? (int) $maximo : 1000;
        $tope = max(1, (int) config('inteligencia.http.tamano_pagina', 50));

        return max(1, min($maximo, $tope));
    }

    /**
     * @param  array<string, mixed>  $metadatos
     */
    public function edicion(array $metadatos): ?Carbon
    {
        $edicion = $metadatos['editingInfo']['dataLastEditDate'] ?? null;

        if (! is_numeric($edicion) || (int) $edicion <= 0) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $edicion)->utc();
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     * @param  array<string, mixed>|null  $poligono
     * @return \Generator<int, list<array<string, mixed>>>
     */
    private function paginasPorIdentificadores(string $url, string $where, ?array $bbox, ?array $poligono, int $tamano): \Generator
    {
        $parametros = $this->parametrosEspaciales($where, $bbox, $poligono);
        $parametros['returnIdsOnly'] = 'true';
        $parametros['returnGeometry'] = 'false';
        $parametros['f'] = 'json';

        $archivo = $this->guardarConsulta($url.'/query', $parametros);

        try {
            $this->afirmarRespuesta($archivo, $url.'/query');
            $datos = $this->decodificarArchivo($archivo, $url.'/query');
        } finally {
            @unlink($archivo);
        }

        $identificadores = $datos['objectIds'] ?? [];

        if ($identificadores === null) {
            return;
        }

        if (! is_array($identificadores)) {
            throw new RuntimeException('La fuente no devolvió identificadores en '.$url.'.');
        }

        $omitidos = [];

        foreach (array_chunk($identificadores, min(max(1, $tamano), self::LOTE_SIN_PAGINACION)) as $lote) {
            try {
                $datos = $this->geometriaDeIdentificadores($url, $lote);
            } catch (ConnectionException|RuntimeException) {
                $omitidos = array_merge($omitidos, array_map(strval(...), $lote));

                continue;
            }

            $entidades = $datos['features'] ?? null;

            if (! is_array($entidades)) {
                throw new RuntimeException('La fuente no devolvió entidades GeoJSON en '.$url.'.');
            }

            if ($entidades !== []) {
                yield $entidades;
            }
        }

        if ($omitidos !== []) {
            throw new RuntimeException(
                'La fuente no entregó geometría para '.count($omitidos).' identificadores (tiempo de espera agotado, sin datos): '
                .implode(', ', $omitidos)
                .'. No se guardó la copia porque quedaría incompleta.',
            );
        }
    }

    /**
     * @param  list<int|string>  $lote
     * @return array<string, mixed>
     */
    private function geometriaDeIdentificadores(string $url, array $lote): array
    {
        $consulta = [
            'objectIds' => implode(',', array_map(strval(...), $lote)),
            'outFields' => '*',
            'returnGeometry' => 'true',
            'f' => 'geojson',
            'outSR' => '4326',
        ];
        $ultimo = null;

        $intentos = max(1, (int) config('inteligencia.http.reintentos', 2)) + 1;
        $espera = max(0, (int) config('inteligencia.http.reintento_ms', 1000));

        for ($intento = 0; $intento < $intentos; $intento++) {
            try {
                $archivo = $this->guardarConsulta($url.'/query', $consulta, false);

                try {
                    $this->afirmarRespuesta($archivo, $url.'/query');

                    return $this->decodificarArchivo($archivo, $url.'/query');
                } finally {
                    @unlink($archivo);
                }
            } catch (ConnectionException $excepcion) {
                $ultimo = $excepcion;

                if ($intento + 1 < $intentos && $espera > 0) {
                    usleep($espera * 1000 * ($intento + 1));
                }
            }
        }

        throw $ultimo ?? new ConnectionException('La fuente no respondió.');
    }

    private function httpSinReintento(int $timeout): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => 'SIID-inteligencia/1.0',
            'Accept' => 'application/json',
            'Connection' => 'close',
        ])->connectTimeout($this->connectTimeout())->timeout($timeout)->withOptions([
            'curl' => [
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout(),
                CURLOPT_TIMEOUT => $timeout,
            ],
        ]);
    }

    /**
     * @param  array{0: float, 1: float, 2: float, 3: float}|null  $bbox
     * @param  array<string, mixed>|null  $poligono
     * @return array<string, mixed>
     */
    private function parametrosEspaciales(string $where, ?array $bbox, ?array $poligono): array
    {
        $parametros = ['where' => $where];

        if ($poligono !== null) {
            $parametros['geometry'] = json_encode($poligono, JSON_THROW_ON_ERROR);
            $parametros['geometryType'] = 'esriGeometryPolygon';
            $parametros['inSR'] = '4326';
            $parametros['spatialRel'] = 'esriSpatialRelIntersects';
        } elseif ($bbox !== null) {
            $parametros['geometry'] = implode(',', $bbox);
            $parametros['geometryType'] = 'esriGeometryEnvelope';
            $parametros['inSR'] = '4326';
            $parametros['spatialRel'] = 'esriSpatialRelIntersects';
        }

        return $parametros;
    }

    private function http(): PendingRequest
    {
        return Http::withHeaders([
            'User-Agent' => 'SIID-inteligencia/1.0',
            'Accept' => 'application/json',
            'Connection' => 'close',
        ])->connectTimeout($this->connectTimeout())
            ->timeout($this->timeoutConsulta())
            ->withOptions([
                'curl' => [
                    CURLOPT_CONNECTTIMEOUT => $this->connectTimeout(),
                    CURLOPT_TIMEOUT => $this->timeoutConsulta(),
                    CURLOPT_LOW_SPEED_LIMIT => 1024,
                    CURLOPT_LOW_SPEED_TIME => 60,
                ],
            ])
            ->retry(
                max(1, (int) config('inteligencia.http.reintentos', 2)) + 1,
                fn (int $intento): int => $intento * max(0, (int) config('inteligencia.http.reintento_ms', 1000)),
                function (Throwable $excepcion): bool {
                    return $excepcion instanceof ConnectionException
                        || ($excepcion instanceof RequestException
                            && ($excepcion->response->serverError() || $excepcion->response->status() === 429));
                },
                true,
            );
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('inteligencia.http.connect_timeout', 30));
    }

    private function timeoutConsulta(): int
    {
        return max(1, (int) config('inteligencia.http.timeout', 180));
    }

    /**
     * @param  array<string, mixed>  $parametros
     */
    private function guardarConsulta(string $url, array $parametros, bool $reintentar = true): string
    {
        $archivo = tempnam(sys_get_temp_dir(), 'arcgis');

        if ($archivo === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal de la consulta.');
        }

        $cliente = $reintentar ? $this->http() : $this->httpSinReintento($this->timeoutConsulta());
        $respuesta = $cliente->asForm()->withOptions(['sink' => $archivo])->post($url, $parametros);
        $respuesta->throw();
        clearstatcache(true, $archivo);

        if (! is_file($archivo) || filesize($archivo) === 0) {
            file_put_contents($archivo, $respuesta->body());
        }

        return $archivo;
    }

    private function afirmarRespuesta(string $archivo, string $url): void
    {
        $prefijo = (string) file_get_contents($archivo, false, null, 0, 8192);

        if (str_contains($prefijo, 'Unauthorized Request Blocked')) {
            throw new RuntimeException('El cortafuegos de la fuente bloqueó la consulta a '.$url.'.');
        }

        if (str_starts_with(ltrim($prefijo), '<')) {
            $texto = (string) file_get_contents($archivo);

            if (preg_match('/class="errorLabel">\s*([^<]+)/', $texto, $coincidencia) === 1) {
                throw new RuntimeException('La fuente respondió un error en '.$url.': '.trim(preg_replace('/\s+/', ' ', $coincidencia[1]) ?? $coincidencia[1]));
            }

            throw new RuntimeException('La fuente no devolvió JSON en '.$url.'.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificarArchivo(string $archivo, string $url): array
    {
        $datos = json_decode((string) file_get_contents($archivo), true);

        if (! is_array($datos)) {
            throw new RuntimeException('La fuente no devolvió JSON en '.$url.'.');
        }

        $error = $datos['error']['message'] ?? null;

        if (is_string($error) && $error !== '') {
            throw new RuntimeException('La fuente respondió un error en '.$url.': '.$error);
        }

        return $datos;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodificar(Response $respuesta, string $url): array
    {
        $respuesta->throw();
        $texto = $respuesta->body();

        if (str_contains($texto, 'Unauthorized Request Blocked')) {
            throw new RuntimeException('El cortafuegos de la fuente bloqueó la consulta a '.$url.'.');
        }

        $datos = json_decode($texto, true);

        if (! is_array($datos)) {
            if (preg_match('/class="errorLabel">\s*([^<]+)/', $texto, $coincidencia) === 1) {
                throw new RuntimeException('La fuente respondió un error en '.$url.': '.trim(preg_replace('/\s+/', ' ', $coincidencia[1]) ?? $coincidencia[1]));
            }

            throw new RuntimeException('La fuente no devolvió JSON en '.$url.'.');
        }

        $error = $datos['error']['message'] ?? null;

        if (is_string($error) && $error !== '') {
            throw new RuntimeException('La fuente respondió un error en '.$url.': '.$error);
        }

        return $datos;
    }

    /**
     * @param  list<array<string, mixed>>  $entidades
     */
    private function primerIdentificador(array $entidades): ?string
    {
        $primera = $entidades[0]['properties'] ?? null;

        if (! is_array($primera)) {
            return isset($entidades[0]['id']) ? (string) $entidades[0]['id'] : null;
        }

        foreach (['OBJECTID', 'objectid', 'FID', 'ap_id'] as $campo) {
            if (isset($primera[$campo]) && $primera[$campo] !== '') {
                return (string) $primera[$campo];
            }
        }

        return null;
    }
}
