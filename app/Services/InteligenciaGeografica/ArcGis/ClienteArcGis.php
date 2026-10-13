<?php

namespace App\Services\InteligenciaGeografica\ArcGis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Consulta metadatos y entidades de un FeatureServer o MapServer de ArcGIS.
 */
class ClienteArcGis
{
    /**
     * Un polígono de estos servicios puede pesar varios megabytes.
     */
    private const LOTE_SIN_PAGINACION = 1;

    /**
     * @return array<string, mixed>
     */
    public function json(string $url): array
    {
        $respuesta = $this->http()->get($url);
        $datos = $this->decodificar($respuesta, $url);

        return $datos;
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

            $respuesta = $this->http()->asForm()->post($url.'/query', $parametros);
            $datos = $this->decodificar($respuesta, $url.'/query');
            $entidades = $datos['features'] ?? null;

            if (! is_array($entidades)) {
                throw new RuntimeException('La fuente no devolvió entidades GeoJSON en '.$url.'.');
            }

            /** @var list<array<string, mixed>> $entidades */
            $primero = $this->primerIdentificador($entidades);

            if ($primero !== null && $primero === $primeroAnterior) {
                throw new RuntimeException('La fuente repitió la misma página. No se puede paginar '.$url.'.');
            }

            $primeroAnterior = $primero;

            if ($entidades !== []) {
                yield $entidades;
            }

            $supera = (bool) ($datos['exceededTransferLimit'] ?? false);
            $desplazamiento += $tamano;
            $pagina++;
            $sigue = $entidades !== [] && ($supera || count($entidades) >= $tamano) && $pagina < 400;
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

        return max(1, min($maximo, 1000));
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

        $respuesta = $this->http()->asForm()->post($url.'/query', $parametros);
        $datos = $this->decodificar($respuesta, $url.'/query');
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

        for ($intento = 0; $intento < 2; $intento++) {
            try {
                $respuesta = $this->httpSinReintento(90)->asForm()->post($url.'/query', $consulta);

                return $this->decodificar($respuesta, $url.'/query');
            } catch (ConnectionException $excepcion) {
                $ultimo = $excepcion;
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
        ])->connectTimeout(10)->timeout($timeout);
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
        ])->connectTimeout(10)->timeout(180)->retry([500], when: function (\Throwable $excepcion): bool {
            return $excepcion instanceof ConnectionException
                || ($excepcion instanceof RequestException
                    && ($excepcion->response->serverError() || $excepcion->response->status() === 429));
        }, throw: true);
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
