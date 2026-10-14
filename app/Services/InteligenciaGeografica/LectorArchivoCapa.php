<?php

namespace App\Services\InteligenciaGeografica;

use InvalidArgumentException;
use ZipArchive;

/**
 * Lee un GeoJSON o un shapefile comprimido para el cargue manual de una capa.
 *
 * @phpstan-type EntidadArchivo array{id: string, atributos: array<string, mixed>, geom: string, srid: int}
 */
class LectorArchivoCapa
{
    /**
     * @return list<EntidadArchivo>
     */
    public function leer(string $nombre, string $contenido): array
    {
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        return match ($extension) {
            'json', 'geojson' => $this->desdeGeoJson($contenido, 4326),
            'zip' => $this->desdeZip($contenido),
            default => throw new InvalidArgumentException('El archivo debe ser GeoJSON o un shapefile .zip.'),
        };
    }

    /**
     * @return list<EntidadArchivo>
     */
    public function desdeGeoJson(string $contenido, int $sridForzado): array
    {
        $datos = json_decode($contenido, true);

        if (! is_array($datos)) {
            throw new InvalidArgumentException('El GeoJSON no se pudo leer.');
        }

        $srid = $this->sridDeCrs(is_array($datos['crs'] ?? null) ? $datos['crs'] : null) ?? $sridForzado;
        $features = $this->features($datos);
        $entidades = [];

        foreach ($features as $indice => $feature) {
            $geometria = $feature['geometry'] ?? null;

            if (! is_array($geometria) || ! isset($geometria['type'], $geometria['coordinates'])) {
                continue;
            }

            $propiedades = is_array($feature['properties'] ?? null) ? $feature['properties'] : [];
            $identificador = $this->identificador($propiedades, $feature['id'] ?? null, $geometria, $indice);
            $entidades[] = [
                'id' => $identificador,
                'atributos' => $this->atributos($propiedades),
                'geom' => json_encode($geometria, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'srid' => $srid,
            ];
        }

        if ($entidades === []) {
            throw new InvalidArgumentException('El archivo no contiene geometrías.');
        }

        return $entidades;
    }

    /**
     * @return list<EntidadArchivo>
     */
    private function desdeZip(string $contenido): array
    {
        $temporal = tempnam(sys_get_temp_dir(), 'capa');

        if ($temporal === false) {
            throw new InvalidArgumentException('No se pudo abrir el archivo comprimido.');
        }

        file_put_contents($temporal, $contenido);
        $zip = new ZipArchive;

        if ($zip->open($temporal) !== true) {
            @unlink($temporal);
            throw new InvalidArgumentException('El archivo comprimido no es un ZIP válido.');
        }

        try {
            $geojson = $this->entrada($zip, ['geojson', 'json']);

            if ($geojson !== null) {
                return $this->desdeGeoJson($geojson, 4326);
            }

            $shp = $this->entrada($zip, ['shp']);
            $dbf = $this->entrada($zip, ['dbf']);
            $prj = $this->entrada($zip, ['prj']);

            if ($shp === null || $dbf === null) {
                throw new InvalidArgumentException('El ZIP debe contener un GeoJSON o un shapefile (.shp y .dbf).');
            }

            return $this->desdeShapefile($shp, $dbf, $prj);
        } finally {
            $zip->close();
            @unlink($temporal);
        }
    }

    private function entrada(ZipArchive $zip, array $extensiones): ?string
    {
        for ($indice = 0; $indice < $zip->numFiles; $indice++) {
            $nombre = (string) $zip->getNameIndex($indice);
            $base = strtolower(basename(str_replace('\\', '/', $nombre)));
            $extension = pathinfo($base, PATHINFO_EXTENSION);

            if (in_array($extension, $extensiones, true)) {
                $contenido = $zip->getFromIndex($indice);

                return is_string($contenido) ? $contenido : null;
            }
        }

        return null;
    }

    /**
     * @return list<EntidadArchivo>
     */
    private function desdeShapefile(string $shp, string $dbf, ?string $prj): array
    {
        $srid = $this->sridDePrj($prj);
        $registros = $this->dbf($dbf);
        $geometrias = $this->shp($shp);

        if (count($geometrias) !== count($registros)) {
            throw new InvalidArgumentException('El shapefile no trae el mismo número de geometrías y atributos.');
        }

        $entidades = [];

        foreach ($geometrias as $indice => $geometria) {
            $propiedades = $registros[$indice];
            $entidades[] = [
                'id' => $this->identificador($propiedades, null, $geometria, $indice),
                'atributos' => $this->atributos($propiedades),
                'geom' => json_encode($geometria, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'srid' => $srid,
            ];
        }

        if ($entidades === []) {
            throw new InvalidArgumentException('El shapefile no contiene geometrías.');
        }

        return $entidades;
    }

    public function sridDePrj(?string $prj): int
    {
        if ($prj === null || trim($prj) === '') {
            throw new InvalidArgumentException('El shapefile no trae .prj. No se asume el sistema de coordenadas.');
        }

        $texto = strtoupper($prj);

        if (str_contains($texto, '9377') || str_contains($texto, 'ORIGEN-NACIONAL') || str_contains($texto, 'ORIGEN_NACIONAL')) {
            return 9377;
        }

        if (str_contains($texto, 'PROJCS')) {
            throw new InvalidArgumentException('La proyección del shapefile no es EPSG:9377.');
        }

        if (str_contains($texto, '4686') || str_contains($texto, 'MAGNA')) {
            return 4686;
        }

        if (str_contains($texto, '4326') || str_contains($texto, 'WGS_1984') || str_contains($texto, 'WGS84')) {
            return 4326;
        }

        throw new InvalidArgumentException('El .prj no es EPSG:4326, EPSG:4686 ni EPSG:9377.');
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    private function features(array $datos): array
    {
        $tipo = $datos['type'] ?? null;

        if ($tipo === 'FeatureCollection' && is_array($datos['features'] ?? null)) {
            return array_values(array_filter($datos['features'], 'is_array'));
        }

        if ($tipo === 'Feature') {
            return [$datos];
        }

        if (isset($datos['type'], $datos['coordinates'])) {
            return [['type' => 'Feature', 'properties' => [], 'geometry' => $datos]];
        }

        throw new InvalidArgumentException('El GeoJSON no es una colección de entidades.');
    }

    /**
     * @param  array<string, mixed>|null  $crs
     */
    private function sridDeCrs(?array $crs): ?int
    {
        $nombre = $crs['properties']['name'] ?? null;

        if (! is_string($nombre)) {
            return null;
        }

        if (str_contains($nombre, '9377')) {
            return 9377;
        }

        if (str_contains($nombre, '4686')) {
            return 4686;
        }

        if (str_contains($nombre, '4326')) {
            return 4326;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $propiedades
     * @param  array<string, mixed>  $geometria
     */
    private function identificador(array $propiedades, mixed $idExplicito, array $geometria, int $indice): string
    {
        foreach (['id', 'ID', 'OBJECTID', 'objectid', 'FID', 'codigo', 'CODIGO', 'n6_cod'] as $campo) {
            if (isset($propiedades[$campo]) && $propiedades[$campo] !== '') {
                return $this->recortar((string) $propiedades[$campo]);
            }
        }

        if (is_scalar($idExplicito) && (string) $idExplicito !== '') {
            return $this->recortar((string) $idExplicito);
        }

        return 'g'.substr(hash('sha256', json_encode($geometria).'|'.$indice), 0, 40);
    }

    /**
     * @param  array<string, mixed>  $propiedades
     * @return array<string, mixed>
     */
    private function atributos(array $propiedades): array
    {
        $limpios = [];

        foreach ($propiedades as $clave => $valor) {
            if (is_string($clave) && ! str_starts_with(strtolower($clave), 'shape')) {
                $limpios[$clave] = is_scalar($valor) || $valor === null ? $valor : json_encode($valor);
            }
        }

        return $limpios;
    }

    private function recortar(string $valor): string
    {
        $valor = trim($valor);

        if (strlen($valor) <= 120) {
            return $valor;
        }

        return substr(hash('sha256', $valor), 0, 40);
    }

    /**
     * @return list<array<string, string>>
     */
    private function dbf(string $contenido): array
    {
        if (strlen($contenido) < 32) {
            throw new InvalidArgumentException('El .dbf está incompleto.');
        }

        $cabecera = unpack('Vregistros/vlongitud_cabecera/vlongitud_registro', substr($contenido, 4, 8));

        if (! is_array($cabecera)) {
            throw new InvalidArgumentException('El .dbf no se pudo leer.');
        }

        $campos = [];
        $cursor = 32;

        while ($cursor + 32 <= (int) $cabecera['longitud_cabecera'] && ord($contenido[$cursor]) !== 0x0D) {
            $nombre = rtrim(substr($contenido, $cursor, 11), "\0");
            $longitud = ord($contenido[$cursor + 16]);
            $campos[] = ['nombre' => $nombre, 'longitud' => $longitud];
            $cursor += 32;
        }

        $registros = [];
        $inicio = (int) $cabecera['longitud_cabecera'];

        for ($fila = 0; $fila < (int) $cabecera['registros']; $fila++) {
            $registro = substr($contenido, $inicio + ($fila * (int) $cabecera['longitud_registro']), (int) $cabecera['longitud_registro']);

            if ($registro === '' || $registro[0] === '*') {
                $registros[] = [];

                continue;
            }

            $valores = [];
            $posicion = 1;

            foreach ($campos as $campo) {
                $valores[$campo['nombre']] = trim(substr($registro, $posicion, $campo['longitud']));
                $posicion += $campo['longitud'];
            }

            $registros[] = $valores;
        }

        return $registros;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function shp(string $contenido): array
    {
        if (strlen($contenido) < 100) {
            throw new InvalidArgumentException('El .shp está incompleto.');
        }

        $geometrias = [];
        $cursor = 100;
        $longitud = strlen($contenido);

        while ($cursor + 8 <= $longitud) {
            $cabecera = unpack('Nnumero/Npalabras', substr($contenido, $cursor, 8));
            $cursor += 8;

            if (! is_array($cabecera)) {
                break;
            }

            $bytes = (int) $cabecera['palabras'] * 2;

            if ($bytes < 4 || $cursor + $bytes > $longitud) {
                throw new InvalidArgumentException('Un registro del shapefile está incompleto.');
            }

            $cuerpo = substr($contenido, $cursor, $bytes);
            $cursor += $bytes;
            $tipo = unpack('V', substr($cuerpo, 0, 4));
            $geometria = $this->geometriaShp(is_array($tipo) ? (int) $tipo[1] : 0, $cuerpo);

            if ($geometria !== null) {
                $geometrias[] = $geometria;
            }
        }

        return $geometrias;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function geometriaShp(int $tipo, string $cuerpo): ?array
    {
        $base = $tipo % 10;

        if ($base === 1 && strlen($cuerpo) >= 20) {
            $punto = unpack('dx/dy', substr($cuerpo, 4, 16));

            return is_array($punto) ? ['type' => 'Point', 'coordinates' => [$punto['x'], $punto['y']]] : null;
        }

        if (! in_array($base, [3, 5, 8], true) || strlen($cuerpo) < 44) {
            return null;
        }

        $conteo = unpack('Vpartes/Vpuntos', substr($cuerpo, 36, 8));

        if (! is_array($conteo)) {
            return null;
        }

        $partes = (int) $conteo['partes'];
        $puntos = (int) $conteo['puntos'];

        if ($base === 8) {
            $partes = 1;
            $puntos = (int) (unpack('V', substr($cuerpo, 36, 4))[1] ?? 0);
        }

        $inicioPartes = $base === 8 ? 40 : 44;
        $indices = $base === 8 ? [0] : [];

        if ($base !== 8) {
            for ($indice = 0; $indice < $partes; $indice++) {
                $valor = unpack('V', substr($cuerpo, $inicioPartes + ($indice * 4), 4));
                $indices[] = is_array($valor) ? (int) $valor[1] : 0;
            }
        }

        $inicioPuntos = $base === 8 ? 40 : 44 + ($partes * 4);
        $coordenadas = [];

        for ($indice = 0; $indice < $puntos; $indice++) {
            $punto = unpack('dx/dy', substr($cuerpo, $inicioPuntos + ($indice * 16), 16));

            if (is_array($punto)) {
                $coordenadas[] = [$punto['x'], $punto['y']];
            }
        }

        if ($base === 8) {
            return ['type' => 'MultiPoint', 'coordinates' => $coordenadas];
        }

        $anillos = [];

        foreach ($indices as $posicion => $desde) {
            $hasta = $indices[$posicion + 1] ?? $puntos;
            $anillo = array_slice($coordenadas, $desde, $hasta - $desde);
            $anillo = $this->cerrar($anillo);

            if (count($anillo) >= ($base === 5 ? 4 : 2)) {
                $anillos[] = $anillo;
            }
        }

        if ($anillos === []) {
            return null;
        }

        if ($base === 3) {
            return count($anillos) === 1
                ? ['type' => 'LineString', 'coordinates' => $anillos[0]]
                : ['type' => 'MultiLineString', 'coordinates' => $anillos];
        }

        return $this->poligonos($anillos);
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $anillos
     * @return array<string, mixed>
     */
    private function poligonos(array $anillos): array
    {
        $poligonos = [];
        $actual = null;

        foreach ($anillos as $anillo) {
            if ($actual === null || ! $this->contiene($actual[0], $anillo[0])) {
                if ($actual !== null) {
                    $poligonos[] = $actual;
                }
                $actual = [$anillo];
            } else {
                $actual[] = $anillo;
            }
        }

        if ($actual !== null) {
            $poligonos[] = $actual;
        }

        if (count($poligonos) === 1) {
            return ['type' => 'Polygon', 'coordinates' => $poligonos[0]];
        }

        return ['type' => 'MultiPolygon', 'coordinates' => $poligonos];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $anillo
     * @param  array{0: float, 1: float}  $punto
     */
    private function contiene(array $anillo, array $punto): bool
    {
        $dentro = false;
        $total = count($anillo);

        for ($i = 0, $j = $total - 1; $i < $total; $j = $i++) {
            $cruza = ($anillo[$i][1] > $punto[1]) !== ($anillo[$j][1] > $punto[1])
                && ($punto[0] < ($anillo[$j][0] - $anillo[$i][0]) * ($punto[1] - $anillo[$i][1]) / (($anillo[$j][1] - $anillo[$i][1]) ?: 1e-12) + $anillo[$i][0]);

            if ($cruza) {
                $dentro = ! $dentro;
            }
        }

        return $dentro;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $anillo
     * @return list<array{0: float, 1: float}>
     */
    private function cerrar(array $anillo): array
    {
        if ($anillo === []) {
            return $anillo;
        }

        $primero = $anillo[0];
        $ultimo = $anillo[array_key_last($anillo)];

        if ($primero[0] !== $ultimo[0] || $primero[1] !== $ultimo[1]) {
            $anillo[] = $primero;
        }

        return $anillo;
    }
}
