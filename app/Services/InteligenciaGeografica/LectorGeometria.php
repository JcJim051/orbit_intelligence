<?php

namespace App\Services\InteligenciaGeografica;

use InvalidArgumentException;
use ZipArchive;

class LectorGeometria
{
    /**
     * Devuelve un GeoJSON de Polygon o MultiPolygon en EPSG:4326.
     */
    public function desdeArchivo(string $nombre, string $contenido): string
    {
        $extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));

        return match ($extension) {
            'kmz' => $this->desdeKml($this->kmlDesdeKmz($contenido)),
            'kml' => $this->desdeKml($contenido),
            'geojson', 'json' => $this->desdeGeoJson($contenido),
            default => throw new InvalidArgumentException('El archivo debe ser KMZ, KML o GeoJSON.'),
        };
    }

    public function desdeGeoJson(string $contenido): string
    {
        $datos = json_decode($contenido, true);

        if (! is_array($datos)) {
            throw new InvalidArgumentException('El GeoJSON no se pudo leer.');
        }

        $poligonos = $this->poligonosDe($datos);

        if ($poligonos === []) {
            throw new InvalidArgumentException('El archivo no contiene un polígono.');
        }

        return $this->geoJsonDePoligonos($poligonos);
    }

    public function desdeKml(string $contenido): string
    {
        $xml = @simplexml_load_string($contenido);

        if ($xml === false) {
            throw new InvalidArgumentException('El KML no se pudo leer.');
        }

        $poligonos = [];

        foreach ($xml->xpath('//*[local-name()="Polygon"]') ?: [] as $poligono) {
            $anillos = [];

            foreach ($poligono->xpath('.//*[local-name()="coordinates"]') ?: [] as $coordenadas) {
                $anillo = $this->anilloDesdeTexto((string) $coordenadas);

                if ($anillo !== []) {
                    $anillos[] = $anillo;
                }
            }

            if ($anillos !== []) {
                $poligonos[] = $anillos;
            }
        }

        if ($poligonos === []) {
            throw new InvalidArgumentException('El KML no contiene un polígono.');
        }

        return $this->geoJsonDePoligonos($poligonos);
    }

    private function kmlDesdeKmz(string $contenido): string
    {
        $temporal = tempnam(sys_get_temp_dir(), 'kmz');

        if ($temporal === false) {
            throw new InvalidArgumentException('No se pudo abrir el KMZ.');
        }

        file_put_contents($temporal, $contenido);
        $zip = new ZipArchive;

        if ($zip->open($temporal) !== true) {
            unlink($temporal);

            throw new InvalidArgumentException('El KMZ no es un archivo comprimido válido.');
        }

        $kml = null;

        for ($indice = 0; $indice < $zip->numFiles; $indice++) {
            $nombre = (string) $zip->getNameIndex($indice);

            if (str_ends_with(strtolower($nombre), '.kml')) {
                $kml = $zip->getFromIndex($indice);

                break;
            }
        }

        $zip->close();
        unlink($temporal);

        if (! is_string($kml) || $kml === '') {
            throw new InvalidArgumentException('El KMZ no contiene un KML.');
        }

        return $kml;
    }

    /**
     * @param  array<string, mixed>  $nodo
     * @return list<list<list<list<float>>>>
     */
    private function poligonosDe(array $nodo): array
    {
        $tipo = $nodo['type'] ?? null;

        if ($tipo === 'FeatureCollection') {
            $poligonos = [];

            foreach ($nodo['features'] ?? [] as $feature) {
                if (is_array($feature)) {
                    $poligonos = [...$poligonos, ...$this->poligonosDe($feature)];
                }
            }

            return $poligonos;
        }

        if ($tipo === 'Feature' && is_array($nodo['geometry'] ?? null)) {
            return $this->poligonosDe($nodo['geometry']);
        }

        if ($tipo === 'Polygon' && is_array($nodo['coordinates'] ?? null)) {
            return [$this->cerrarAnillos($nodo['coordinates'])];
        }

        if ($tipo === 'MultiPolygon' && is_array($nodo['coordinates'] ?? null)) {
            return array_map($this->cerrarAnillos(...), $nodo['coordinates']);
        }

        if ($tipo === 'GeometryCollection') {
            $poligonos = [];

            foreach ($nodo['geometries'] ?? [] as $geometria) {
                if (is_array($geometria)) {
                    $poligonos = [...$poligonos, ...$this->poligonosDe($geometria)];
                }
            }

            return $poligonos;
        }

        return [];
    }

    /**
     * @param  list<list<list<float>>>  $anillos
     * @return list<list<list<float>>>
     */
    private function cerrarAnillos(array $anillos): array
    {
        return array_values(array_filter(array_map(function (mixed $anillo): array {
            if (! is_array($anillo)) {
                return [];
            }

            $puntos = array_values(array_filter(array_map(function (mixed $punto): array {
                if (! is_array($punto) || ! isset($punto[0], $punto[1]) || ! is_numeric($punto[0]) || ! is_numeric($punto[1])) {
                    return [];
                }

                return [(float) $punto[0], (float) $punto[1]];
            }, $anillo)));

            if (count($puntos) < 4) {
                return [];
            }

            $primero = $puntos[0];
            $ultimo = $puntos[array_key_last($puntos)];

            if ($primero !== $ultimo) {
                $puntos[] = $primero;
            }

            return $puntos;
        }, $anillos)));
    }

    /**
     * @return list<list<float>>
     */
    private function anilloDesdeTexto(string $texto): array
    {
        $puntos = [];

        foreach (preg_split('/\s+/', trim($texto)) ?: [] as $trio) {
            if ($trio === '') {
                continue;
            }

            $partes = explode(',', $trio);

            if (count($partes) < 2 || ! is_numeric($partes[0]) || ! is_numeric($partes[1])) {
                continue;
            }

            $puntos[] = [(float) $partes[0], (float) $partes[1]];
        }

        if (count($puntos) < 3) {
            return [];
        }

        $primero = $puntos[0];
        $ultimo = $puntos[array_key_last($puntos)];

        if ($primero !== $ultimo) {
            $puntos[] = $primero;
        }

        return count($puntos) >= 4 ? $puntos : [];
    }

    /**
     * @param  list<list<list<list<float>>>>  $poligonos
     */
    private function geoJsonDePoligonos(array $poligonos): string
    {
        $poligonos = array_values(array_filter($poligonos));

        if ($poligonos === []) {
            throw new InvalidArgumentException('El archivo no contiene un polígono.');
        }

        $geometria = count($poligonos) === 1
            ? ['type' => 'Polygon', 'coordinates' => $poligonos[0]]
            : ['type' => 'MultiPolygon', 'coordinates' => $poligonos];

        return json_encode($geometria, JSON_THROW_ON_ERROR);
    }

    public function afirmarCoordenadas(string $geoJson, int $srid): void
    {
        if (! in_array($srid, [4326, 9377], true)) {
            throw new InvalidArgumentException('Solo se acepta EPSG:4326 o EPSG:9377.');
        }

        $datos = json_decode($geoJson, true);

        if (! is_array($datos)) {
            throw new InvalidArgumentException('El GeoJSON no se pudo leer.');
        }

        $this->recorrerCoordenadas($datos, function (float $x, float $y) use ($srid): void {
            if ($srid === 4326 && (abs($x) > 180 || abs($y) > 90)) {
                throw new InvalidArgumentException('Las coordenadas no están en grados (EPSG:4326).');
            }

            if ($srid === 9377 && abs($x) <= 180 && abs($y) <= 90) {
                throw new InvalidArgumentException('Estas coordenadas parecen geográficas, no metros de EPSG:9377.');
            }

            if ($srid === 9377 && (abs($x) > 20000000 || abs($y) > 20000000)) {
                throw new InvalidArgumentException('Las coordenadas quedan fuera del rango de EPSG:9377.');
            }
        });
    }

    /**
     * @param  array<mixed>  $nodo
     * @param  callable(float, float): void  $visitar
     */
    private function recorrerCoordenadas(array $nodo, callable $visitar): void
    {
        if (isset($nodo[0], $nodo[1]) && is_numeric($nodo[0]) && is_numeric($nodo[1]) && ! is_array($nodo[0])) {
            $visitar((float) $nodo[0], (float) $nodo[1]);

            return;
        }

        foreach ($nodo as $hijo) {
            if (is_array($hijo)) {
                $this->recorrerCoordenadas($hijo, $visitar);
            }
        }
    }
}
