<?php

namespace App\Services\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;

class ExportarCruces
{
    /**
     * @return array{bytes: string, nombre: string}|null
     */
    public function archivo(AnalisisArea $analisis, ?Capa $capa, string $formato): ?array
    {
        if (! in_array($formato, ['geojson', 'kml', 'shp'], true)) {
            return null;
        }

        $capas = $this->capas($analisis, $capa);

        if ($capa !== null && ($capas === [] || $capas[0]['entidades'] === [])) {
            return null;
        }

        $metadatos = $this->metadatos($capas);
        $nombre = $capa === null
            ? 'cruces-analisis-'.$analisis->id.'.zip'
            : 'cruces-'.$capa->codigo.'.zip';

        $archivos = match ($formato) {
            'geojson' => ['cruces.geojson' => $this->geojson($capas)],
            'kml' => ['cruces.kml' => $this->kml($capas)],
            'shp' => $this->shapefile($capas),
        };
        $archivos['metadatos.txt'] = $metadatos;

        return [
            'bytes' => $this->zip($archivos),
            'nombre' => $nombre,
        ];
    }

    /**
     * @return list<array{etiqueta: string, disponible: bool, fuente: string, licencia: string, fecha_corte: string, fecha_consulta: string, entidades: list<array{geometria: array<string, mixed>, propiedades: array<string, float|int|string>}>}>
     */
    private function capas(AnalisisArea $analisis, ?Capa $capa): array
    {
        $consulta = Capa::query()->where('activa', true)->orderBy('codigo');

        if ($capa !== null) {
            $consulta->whereKey($capa->id);
        }

        $capas = [];

        foreach ($consulta->get() as $candidata) {
            $capas[] = $this->capa($analisis, $candidata);
        }

        return $capas;
    }

    /**
     * @return array{etiqueta: string, disponible: bool, fuente: string, licencia: string, fecha_corte: string, fecha_consulta: string, entidades: list<array{geometria: array<string, mixed>, propiedades: array<string, float|int|string>}>}
     */
    private function capa(AnalisisArea $analisis, Capa $capa): array
    {
        $resultado = AnalisisResultado::query()
            ->where('analisis_area_id', $analisis->id)
            ->where('capa_id', $capa->id)
            ->with('version')
            ->first();
        $vacia = [
            'etiqueta' => $capa->etiqueta(),
            'disponible' => false,
            'fuente' => '',
            'licencia' => '',
            'fecha_corte' => '',
            'fecha_consulta' => '',
            'entidades' => [],
        ];

        if ($resultado === null || $resultado->version === null || (int) $resultado->conteo < 1) {
            if ($resultado !== null) {
                $vacia['disponible'] = true;
            }

            return $vacia;
        }

        $zona = (string) config('app.timezone');
        $fuente = (string) ($resultado->cita_fuente ?: 'Sin cita');
        $licencia = (string) ($resultado->licencia ?: 'Sin licencia');
        $corte = $resultado->fecha_corte?->timezone($zona)->format('Y-m-d H:i:s') ?? '';
        $consulta = $resultado->consulted_at?->timezone($zona)->format('Y-m-d H:i:s') ?? '';

        return [
            'etiqueta' => $capa->etiqueta(),
            'disponible' => true,
            'fuente' => $fuente,
            'licencia' => $licencia,
            'fecha_corte' => $corte,
            'fecha_consulta' => $consulta,
            'entidades' => $this->entidades($analisis, $capa, $resultado->version->numero, [
                'capa' => $capa->etiqueta(),
                'fuente' => $fuente,
                'fecha_corte' => $corte,
                'fecha_consulta' => $consulta,
            ]),
        ];
    }

    /**
     * @param  array{capa: string, fuente: string, fecha_corte: string, fecha_consulta: string}  $extra
     * @return list<array{geometria: array<string, mixed>, propiedades: array<string, float|int|string>}>
     */
    private function entidades(AnalisisArea $analisis, Capa $capa, int $numero, array $extra): array
    {
        $filas = DB::select(
            'select cache.atributos::text as atributos,
                    ST_Dimension(cache.geom) as dimension,
                    ST_AsGeoJSON(ST_Transform(recorte.geom, 4326)) as geojson,
                    ST_Area(recorte.geom) / 10000 as ha,
                    ST_Length(recorte.geom) / 1000 as km
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             cross join lateral (
                select case ST_Dimension(cache.geom)
                    when 0 then ST_CollectionExtract(ST_Intersection(cache.geom, area.geom), 1)
                    when 1 then ST_CollectionExtract(ST_Intersection(cache.geom, area.geom), 2)
                    else ST_CollectionExtract(ST_Intersection(cache.geom, area.geom), 3)
                end as geom
             ) recorte
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
               and ST_Intersects(cache.geom, area.geom)
               and recorte.geom is not null
               and not ST_IsEmpty(recorte.geom)
             order by cache.identificador_origen',
            [$analisis->id, $capa->id, $numero, $numero],
        );
        $entidades = [];

        foreach ($filas as $fila) {
            $geometria = json_decode((string) $fila->geojson, true);

            if (! is_array($geometria)) {
                continue;
            }

            $atributos = json_decode((string) $fila->atributos, true);
            $propiedades = $this->propiedades(is_array($atributos) ? $atributos : [], $extra);
            $dimension = (int) $fila->dimension;

            if ($dimension === 1) {
                $propiedades['km'] = round((float) $fila->km, 4);
            } elseif ($dimension >= 2) {
                $propiedades['ha'] = round((float) $fila->ha, 4);
            }

            $entidades[] = [
                'geometria' => $geometria,
                'propiedades' => $propiedades,
            ];
        }

        return $entidades;
    }

    /**
     * @param  array<mixed>  $atributos
     * @param  array<string, string>  $extra
     * @return array<string, float|int|string>
     */
    private function propiedades(array $atributos, array $extra): array
    {
        $planas = [];

        foreach ($atributos as $clave => $valor) {
            if (! is_string($clave) || $clave === '') {
                continue;
            }

            if (is_array($valor)) {
                $planas[$clave] = json_encode($valor, JSON_UNESCAPED_UNICODE) ?: '';
            } elseif (is_bool($valor)) {
                $planas[$clave] = $valor ? 'true' : 'false';
            } elseif (is_int($valor) || is_float($valor)) {
                $planas[$clave] = $valor;
            } elseif ($valor !== null && ! is_object($valor)) {
                $planas[$clave] = (string) $valor;
            }
        }

        foreach ($extra as $clave => $valor) {
            $planas[$clave] = $valor;
        }

        return $planas;
    }

    /**
     * @param  list<array{entidades: list<array{geometria: array<string, mixed>, propiedades: array<string, float|int|string>}>}>  $capas
     */
    private function geojson(array $capas): string
    {
        $features = [];

        foreach ($capas as $capa) {
            foreach ($capa['entidades'] as $entidad) {
                $features[] = [
                    'type' => 'Feature',
                    'geometry' => $entidad['geometria'],
                    'properties' => $entidad['propiedades'],
                ];
            }
        }

        return json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"type":"FeatureCollection","features":[]}';
    }

    /**
     * @param  list<array{entidades: list<array{geometria: array<string, mixed>, propiedades: array<string, float|int|string>}>}>  $capas
     */
    private function kml(array $capas): string
    {
        $marcas = '';

        foreach ($capas as $capa) {
            foreach ($capa['entidades'] as $entidad) {
                $datos = '';

                foreach ($entidad['propiedades'] as $clave => $valor) {
                    $datos .= '<Data name="'.e($clave).'"><value>'.e((string) $valor).'</value></Data>';
                }

                $nombre = (string) ($entidad['propiedades']['capa'] ?? 'Cruce');
                $marcas .= '<Placemark><name>'.e($nombre).'</name><ExtendedData>'.$datos.'</ExtendedData>'
                    .$this->geometriaKml($entidad['geometria']).'</Placemark>';
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<kml xmlns="http://www.opengis.net/kml/2.2"><Document>'.$marcas.'</Document></kml>';
    }

    /**
     * @param  array<string, mixed>  $geometria
     */
    private function geometriaKml(array $geometria): string
    {
        $tipo = $geometria['type'] ?? null;
        $coordenadas = $geometria['coordinates'] ?? null;

        if ($tipo === 'GeometryCollection') {
            $partes = '';

            foreach ($geometria['geometries'] ?? [] as $parte) {
                if (is_array($parte)) {
                    $partes .= $this->geometriaKml($parte);
                }
            }

            return '<MultiGeometry>'.$partes.'</MultiGeometry>';
        }

        if (! is_array($coordenadas)) {
            return '';
        }

        return match ($tipo) {
            'Point' => '<Point><coordinates>'.$this->puntoKml($coordenadas).'</coordinates></Point>',
            'LineString' => '<LineString><coordinates>'.$this->lineaKml($coordenadas).'</coordinates></LineString>',
            'Polygon' => '<Polygon><outerBoundaryIs><LinearRing><coordinates>'.$this->lineaKml(is_array($coordenadas[0] ?? null) ? $coordenadas[0] : []).'</coordinates></LinearRing></outerBoundaryIs></Polygon>',
            'MultiLineString' => '<MultiGeometry>'.$this->unirKml($coordenadas, 'LineString').'</MultiGeometry>',
            'MultiPolygon' => '<MultiGeometry>'.$this->unirKml($coordenadas, 'Polygon').'</MultiGeometry>',
            default => '',
        };
    }

    /**
     * @param  list<mixed>  $partes
     */
    private function unirKml(array $partes, string $tipo): string
    {
        $kml = '';

        foreach ($partes as $parte) {
            if (! is_array($parte)) {
                continue;
            }

            $kml .= $this->geometriaKml([
                'type' => $tipo,
                'coordinates' => $parte,
            ]);
        }

        return $kml;
    }

    /**
     * @param  list<mixed>  $punto
     */
    private function puntoKml(array $punto): string
    {
        if (! is_numeric($punto[0] ?? null) || ! is_numeric($punto[1] ?? null)) {
            return '';
        }

        return $punto[0].','.$punto[1].',0';
    }

    /**
     * @param  list<mixed>  $linea
     */
    private function lineaKml(array $linea): string
    {
        $texto = [];

        foreach ($linea as $punto) {
            if (is_array($punto)) {
                $coordenada = $this->puntoKml($punto);

                if ($coordenada !== '') {
                    $texto[] = $coordenada;
                }
            }
        }

        return implode(' ', $texto);
    }

    /**
     * @param  list<array{etiqueta: string, entidades: list<array{geometria: array<string, mixed>, propiedades: array<string, float|int|string>}>}>  $capas
     * @return array<string, string>
     */
    private function shapefile(array $capas): array
    {
        $ogr = $this->ogr2ogr();
        $directorio = sys_get_temp_dir().'/cruces-'.bin2hex(random_bytes(4));
        mkdir($directorio);
        $archivos = [];

        try {
            foreach ($capas as $indice => $capa) {
                if ($capa['entidades'] === []) {
                    continue;
                }

                $porTipo = [];

                foreach ($capa['entidades'] as $entidad) {
                    $tipo = $this->tipoShapefile($entidad['geometria']['type'] ?? '');
                    $porTipo[$tipo][] = $entidad;
                }

                foreach ($porTipo as $tipo => $entidades) {
                    $base = count($porTipo) === 1 ? 'capa_'.$indice : 'capa_'.$indice.'_'.$tipo;
                    $geojson = $directorio.'/'.$base.'.geojson';
                    file_put_contents($geojson, $this->geojson([['entidades' => $entidades]]));
                    $proceso = new Process([
                        $ogr, '-f', 'ESRI Shapefile', $directorio.'/'.$base.'.shp', $geojson,
                        '-t_srs', 'EPSG:4326', '-lco', 'ENCODING=UTF-8', '-nlt', 'PROMOTE_TO_MULTI',
                    ]);
                    $proceso->setTimeout(60);

                    try {
                        $proceso->mustRun();
                    } catch (ProcessFailedException) {
                        throw new RuntimeException('No se pudo generar el shapefile.');
                    }

                    if (! is_file($directorio.'/'.$base.'.cpg')) {
                        file_put_contents($directorio.'/'.$base.'.cpg', "UTF-8\n");
                    }

                    foreach (['shp', 'shx', 'dbf', 'prj', 'cpg'] as $extension) {
                        $ruta = $directorio.'/'.$base.'.'.$extension;

                        if (is_file($ruta)) {
                            $archivos[$base.'.'.$extension] = (string) file_get_contents($ruta);
                        }
                    }
                }
            }
        } finally {
            foreach (glob($directorio.'/*') ?: [] as $ruta) {
                if (is_file($ruta)) {
                    unlink($ruta);
                }
            }

            if (is_dir($directorio)) {
                rmdir($directorio);
            }
        }

        return $archivos;
    }

    private function tipoShapefile(string $tipo): string
    {
        return match ($tipo) {
            'LineString', 'MultiLineString' => 'linea',
            'Point', 'MultiPoint' => 'punto',
            default => 'poligono',
        };
    }

    private function ogr2ogr(): string
    {
        $rutas = $this->rutasOgr2ogr();

        foreach ($rutas as $ruta) {
            if ($this->esBinario($ruta)) {
                return $ruta;
            }
        }

        throw new Ogr2OgrNoDisponible($rutas);
    }

    /**
     * @return list<string>
     */
    protected function rutasOgr2ogr(): array
    {
        $configurada = config('inteligencia.ogr2ogr');
        $rutas = [
            is_string($configurada) ? trim($configurada) : '',
            '/opt/homebrew/bin/ogr2ogr',
            '/usr/local/bin/ogr2ogr',
            '/usr/bin/ogr2ogr',
            trim((string) shell_exec('command -v ogr2ogr')),
        ];
        $unicas = [];

        foreach ($rutas as $ruta) {
            if ($ruta !== '' && ! in_array($ruta, $unicas, true)) {
                $unicas[] = $ruta;
            }
        }

        return $unicas;
    }

    private function esBinario(string $ruta): bool
    {
        return is_file($ruta) && is_executable($ruta);
    }

    /**
     * @param  list<array{etiqueta: string, disponible: bool, fuente: string, licencia: string, fecha_corte: string, fecha_consulta: string, entidades: list<mixed>}>  $capas
     */
    private function metadatos(array $capas): string
    {
        $lineas = [
            'Sistema de referencia: EPSG:4326.',
            'La geometría es la intersección de cada entidad con el polígono analizado.',
            'En el shapefile los nombres de campo se recortan a 10 caracteres: fecha_corte queda fecha_cort y fecha_consulta queda fecha_cons.',
            '',
        ];

        foreach ($capas as $capa) {
            if (! $capa['disponible']) {
                $lineas[] = $capa['etiqueta'].': fuente no disponible';

                continue;
            }

            if ($capa['entidades'] === []) {
                $lineas[] = $capa['etiqueta'].': Sin cruce';

                continue;
            }

            $lineas[] = $capa['etiqueta'];
            $lineas[] = 'Fuente: '.$capa['fuente'];
            $lineas[] = 'Licencia: '.$capa['licencia'];
            $lineas[] = 'Fecha de corte: '.$capa['fecha_corte'];
            $lineas[] = 'Fecha de consulta: '.$capa['fecha_consulta'];
            $lineas[] = 'Entidades: '.count($capa['entidades']);
            $lineas[] = '';
        }

        return implode("\n", $lineas)."\n";
    }

    /**
     * @param  array<string, string>  $archivos
     */
    private function zip(array $archivos): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cruces');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo crear el archivo de cruces.');
        }

        $zip = new ZipArchive;
        $zip->open($ruta, ZipArchive::OVERWRITE);

        foreach ($archivos as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }

        $zip->close();
        $bytes = (string) file_get_contents($ruta);
        unlink($ruta);

        return $bytes;
    }
}
