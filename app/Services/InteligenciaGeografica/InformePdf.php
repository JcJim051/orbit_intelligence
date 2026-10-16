<?php

namespace App\Services\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Services\InteligenciaGeografica\Conectores\CatalogoCapasAbiertas;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class InformePdf
{
    /**
     * Campos monetarios del avalúo. TIPO_AVALUO no entra: en el paquete es un texto del perímetro urbano, no un valor.
     *
     * @var list<string>
     */
    private const CAMPOS_AVALUO = ['AVALUO', 'AVALUO_CATASTRAL', 'VALOR_AVALUO'];

    public function __construct(
        private InformeAnalisis $informe,
        private CatalogoCapasAbiertas $catalogo,
        private MapaBaseAnalisis $mapaBase,
    ) {}

    public function pdf(AnalisisArea $analisis): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($analisis));
        $dompdf->setPaper('a4');
        $dompdf->render();

        return $dompdf->output();
    }

    public function html(AnalisisArea $analisis): string
    {
        return view('exports.informe-analisis', $this->datos($analisis))->render();
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(AnalisisArea $analisis): array
    {
        $analisis->loadMissing('usuario');
        $filas = $this->informe->filas($analisis);
        $sincronizaciones = $this->ultimasSincronizaciones($filas->pluck('id')->filter()->all());
        $areas = $this->areas($analisis);
        $capas = [];

        foreach ($filas as $fila) {
            $capas[] = $this->capa($analisis, $fila, $sincronizaciones->get($fila['id'] ?? 0), $areas['analizada']);
        }

        return [
            'fecha' => $analisis->fecha?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin fecha',
            'usuario' => $analisis->usuario?->name ?: 'Sin usuario',
            'municipios' => $this->municipios($analisis),
            'area_total' => $this->numero($areas['total']),
            'area_analizada' => $this->numero($areas['analizada']),
            'ha_fuera' => $this->numero((float) $analisis->ha_fuera_meta),
            'pct_fuera' => $this->numero((float) $analisis->pct_fuera_meta),
            'nota_fuera' => $analisis->textoFueraDelMeta(),
            'mapa' => $this->mapa($analisis, $filas->all()),
            'capas' => $capas,
            'alertas' => $this->alertas($capas),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return Collection<int, CapaSincronizacion>
     */
    private function ultimasSincronizaciones(array $ids)
    {
        if ($ids === []) {
            return collect();
        }

        return CapaSincronizacion::query()
            ->whereIn('capa_id', $ids)
            ->orderByDesc('id')
            ->get()
            ->unique('capa_id')
            ->keyBy('capa_id');
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    private function capa(AnalisisArea $analisis, array $fila, ?CapaSincronizacion $sincronizacion, float $areaAnalizada): array
    {
        $capa = Capa::query()->find($fila['id'] ?? null);
        $codigo = (string) ($capa?->codigo ?? '');
        $definicion = $this->catalogo->definicion($codigo);
        $disponible = ($fila['pendiente'] ?? true) === false;
        $numero = $disponible ? $this->numeroVersion($analisis, (int) $fila['id']) : null;
        $cruces = $numero === null ? [] : $this->cruces($analisis, (int) $fila['id'], $numero);
        $detalles = $this->detalles($codigo, $cruces);
        $hectareas = $numero === null ? null : $this->areaUnida($analisis, (int) $fila['id'], $numero);
        $porcentaje = ($hectareas !== null && $areaAnalizada > 0) ? min(100, ($hectareas / $areaAnalizada) * 100) : null;
        $clases = $codigo === 'CORMACARENA_POT_CLASIFICACION' && $numero !== null
            ? $this->clasesSuelo($analisis, (int) $fila['id'], $numero, $areaAnalizada)
            : [];
        $url = $this->urlDe($fila, $capa, $definicion?->urlServicio);
        $intento = $sincronizacion?->finalizado_at ?? $sincronizacion?->iniciado_at;

        return [
            'codigo' => $codigo,
            'nombre' => (string) ($fila['capa'] ?? $codigo),
            'disponible' => $disponible,
            'conteo' => $disponible ? $fila['conteo'] : null,
            'area_ha' => $hectareas === null ? null : $this->numero($hectareas),
            'porcentaje' => $porcentaje === null ? null : $this->numero($porcentaje, 1),
            'columnas' => $detalles['columnas'],
            'filas' => $detalles['filas'],
            'clases' => $clases,
            'recorte' => $disponible && is_int($fila['conteo'] ?? null) && count($detalles['filas']) < $fila['conteo'],
            'avaluo' => $codigo === 'IGAC_CATASTRO_R1' && $disponible ? $this->avaluo($analisis, $fila) : null,
            'fuente' => $this->fuenteDe($fila, $capa, $url),
            'url' => $url,
            'licencia' => $fila['licencia'] ?: ($capa?->licencia ?: 'Sin licencia'),
            'fecha_corte' => $fila['fecha_corte'] ?: ($capa?->fecha_corte_fuente?->format('d/m/Y H:i') ?? 'Sin fecha de corte'),
            'consultado' => $fila['consulted_at'] ?: ($intento?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin fecha de consulta'),
            'origen' => $disponible ? ($fila['origen'] ?: 'Sin origen') : 'fuente no disponible',
            'vigencia' => $disponible ? ($fila['vigencia'] ?: 'Sin vigencia') : 'fuente no disponible',
            'motivo' => $sincronizacion?->mensaje_error ?: ($sincronizacion?->estado->label() ?? 'No hay intentos de sincronización.'),
            'intento' => $intento?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin intento registrado',
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function urlDe(array $fila, ?Capa $capa, ?string $urlCatalogo): string
    {
        foreach ([$fila['url_fuente'] ?? null, $capa?->url_servicio, $urlCatalogo] as $candidata) {
            if (is_string($candidata) && preg_match('#^https?://#', $candidata) === 1) {
                return $candidata;
            }
        }

        return 'Sin URL';
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function fuenteDe(array $fila, ?Capa $capa, string $url): string
    {
        $fuente = (string) ($fila['cita_fuente'] ?: ($capa?->cita_fuente ?: 'Sin cita'));

        if ($url !== 'Sin URL') {
            $fuente = trim(str_replace(['URL pendiente de confirmación.', 'URL pendiente de confirmación'], '', $fuente));
            $fuente = trim(preg_replace('/\s{2,}/', ' ', $fuente) ?? $fuente);
        }

        return $fuente !== '' ? $fuente : 'Sin cita';
    }

    /**
     * @param  list<array{atributos: string, ha: float}>  $cruces
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    private function detalles(string $codigo, array $cruces): array
    {
        $columnas = $this->columnas($codigo);

        if ($cruces === []) {
            return ['columnas' => [], 'filas' => []];
        }

        $filas = [];

        foreach ($cruces as $cruce) {
            $atributos = json_decode($cruce['atributos'], true);
            $atributos = is_array($atributos) ? $atributos : [];
            $celdas = [];

            foreach ($columnas as $columna) {
                $celdas[] = $this->valor($atributos, $columna['claves'], $columna['etiquetas']) ?? '—';
            }

            $celdas[] = $this->numero($cruce['ha']);
            $filas[] = $celdas;
        }

        return [
            'columnas' => [...array_column($columnas, 'titulo'), 'ha'],
            'filas' => $filas,
        ];
    }

    private function numeroVersion(AnalisisArea $analisis, int $capaId): ?int
    {
        return AnalisisResultado::query()
            ->where('analisis_area_id', $analisis->id)
            ->where('capa_id', $capaId)
            ->with('version')
            ->first()
            ?->version
            ?->numero;
    }

    private function areaUnida(AnalisisArea $analisis, int $capaId, int $numero): ?float
    {
        $fila = DB::selectOne(
            'select ST_Area(ST_Union(ST_Intersection(cache.geom, area.geom))) / 10000 as ha
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
               and ST_Intersects(cache.geom, area.geom)',
            [$analisis->id, $capaId, $numero, $numero],
        );

        return $fila?->ha === null ? null : (float) $fila->ha;
    }

    /**
     * @return list<array{atributos: string, ha: float}>
     */
    private function cruces(AnalisisArea $analisis, int $capaId, int $numero): array
    {
        $filas = DB::select(
            'select cache.atributos::text as atributos,
                    ST_Area(ST_Intersection(cache.geom, area.geom)) / 10000 as ha
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
               and ST_Intersects(cache.geom, area.geom)
             order by cache.identificador_origen
             limit 100',
            [$analisis->id, $capaId, $numero, $numero],
        );

        return array_map(fn (object $fila): array => [
            'atributos' => (string) $fila->atributos,
            'ha' => (float) $fila->ha,
        ], $filas);
    }

    /**
     * @return list<array{clase: string, ha: string, porcentaje: string, restriccion: bool}>
     */
    private function clasesSuelo(AnalisisArea $analisis, int $capaId, int $numero, float $areaAnalizada): array
    {
        $filas = DB::select(
            "select coalesce(cache.atributos->>'Clasificacion', 'Sin clase') as clase,
                    ST_Area(ST_Union(ST_Intersection(cache.geom, area.geom))) / 10000 as ha
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
               and ST_Intersects(cache.geom, area.geom)
             group by 1
             order by 1",
            [$analisis->id, $capaId, $numero, $numero],
        );

        return array_map(function (object $fila) use ($areaAnalizada): array {
            $hectareas = (float) $fila->ha;
            $porcentaje = $areaAnalizada > 0 ? min(100, ($hectareas / $areaAnalizada) * 100) : 0;

            return [
                'clase' => (string) $fila->clase,
                'ha' => $this->numero($hectareas),
                'porcentaje' => $this->numero($porcentaje, 1),
                'restriccion' => $this->esRestriccion((string) $fila->clase),
            ];
        }, $filas);
    }

    private function esRestriccion(string $clase): bool
    {
        $texto = mb_strtolower($clase);
        $marcas = config('inteligencia.restricciones_suelo', []);

        foreach (is_array($marcas) ? $marcas : [] as $marca) {
            if (is_string($marca) && $marca !== '' && str_contains($texto, mb_strtolower($marca))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{titulo: string, claves: list<string>, etiquetas: array<string, string>}>
     */
    private function columnas(string $codigo): array
    {
        $nombres = $this->nombresDeCapa($codigo);

        return match ($codigo) {
            'ANM_TITULOS_SOLICITUDES' => [
                ['titulo' => 'Tipo', 'claves' => ['_capa'], 'etiquetas' => $nombres],
                ['titulo' => 'Expediente', 'claves' => ['CODIGO_EXPEDIENTE'], 'etiquetas' => []],
                ['titulo' => 'Mineral', 'claves' => ['MINERALES'], 'etiquetas' => []],
                ['titulo' => 'Titular o solicitante', 'claves' => ['NOMBRE_DE_TITULAR', 'NOMBRE_SOLICITANTE'], 'etiquetas' => []],
                ['titulo' => 'Estado', 'claves' => ['ESTADO'], 'etiquetas' => []],
            ],
            'RUNAP_AREAS_PROTEGIDAS' => [
                ['titulo' => 'Área', 'claves' => ['ap_nombre'], 'etiquetas' => []],
                ['titulo' => 'Categoría', 'claves' => ['ap_categoria'], 'etiquetas' => []],
            ],
            'CORMACARENA_POT_CLASIFICACION' => [
                ['titulo' => 'Clase de suelo', 'claves' => ['Clasificacion'], 'etiquetas' => []],
            ],
            'IDEAM_COBERTURA_TIERRA_2024' => [
                ['titulo' => 'Cobertura', 'claves' => ['leyenda'], 'etiquetas' => []],
            ],
            'ANT_RESGUARDOS_CONSEJOS' => [
                ['titulo' => 'Territorio', 'claves' => ['etiqueta'], 'etiquetas' => []],
            ],
            'META_DRENAJE' => [
                ['titulo' => 'Nombre', 'claves' => ['NOMBRE_GEO'], 'etiquetas' => []],
                ['titulo' => 'Tipo', 'claves' => ['_capa'], 'etiquetas' => $nombres],
            ],
            'CORMACARENA_ECOSISTEMAS' => [
                ['titulo' => 'Ecosistema', 'claves' => ['_capa'], 'etiquetas' => $nombres],
            ],
            'SGC_AMENAZA_MOVIMIENTOS_MASA' => [
                ['titulo' => 'Nivel de amenaza', 'claves' => ['CATAME'], 'etiquetas' => []],
            ],
            'DANE_GRILLA_POBLACION_1KM' => [
                ['titulo' => 'Población estimada', 'claves' => ['personas'], 'etiquetas' => []],
            ],
            'IGAC_CATASTRO_R1' => [
                ['titulo' => 'Predio', 'claves' => ['CODIGO'], 'etiquetas' => []],
                ['titulo' => 'Municipio', 'claves' => ['codigo_municipio', 'CODIGO_MUNICIPIO'], 'etiquetas' => []],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    private function nombresDeCapa(string $codigo): array
    {
        $definicion = $this->catalogo->definicion($codigo);
        $nombres = [];

        foreach ($definicion?->endpoints ?? [] as $endpoint) {
            $nombres[$endpoint['clave']] = $endpoint['nombre'];
        }

        return $nombres;
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<string>  $claves
     * @param  array<string, string>  $etiquetas
     */
    private function valor(array $atributos, array $claves, array $etiquetas): ?string
    {
        foreach ($claves as $clave) {
            if (! array_key_exists($clave, $atributos) || $atributos[$clave] === null || $atributos[$clave] === '') {
                continue;
            }

            $texto = trim((string) $atributos[$clave]);

            return $etiquetas[$texto] ?? $texto;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{incluido: bool, texto?: string, campo?: string, total?: string, por_predio?: string, por_ha?: string, predios?: int}
     */
    private function avaluo(AnalisisArea $analisis, array $fila): array
    {
        if (($fila['pendiente'] ?? true) === true) {
            return ['incluido' => false, 'texto' => 'El paquete abierto del IGAC no incluye avalúo catastral'];
        }

        $resultado = AnalisisResultado::query()
            ->where('analisis_area_id', $analisis->id)
            ->where('capa_id', $fila['id'])
            ->with('version')
            ->first();
        $numero = $resultado?->version?->numero;
        $muestra = $numero === null ? null : DB::selectOne(
            'select cache.atributos as atributos
             from inteligencia.capa_objeto_cache cache
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
             limit 1',
            [$fila['id'], $numero, $numero],
        );
        $atributos = json_decode((string) ($muestra->atributos ?? '{}'), true);
        $campo = $this->campoAvaluo(is_array($atributos) ? $atributos : []);

        if ($campo === null || $numero === null) {
            return ['incluido' => false, 'texto' => 'El paquete abierto del IGAC no incluye avalúo catastral'];
        }

        $suma = DB::selectOne(
            "select count(*) filter (where (cache.atributos->>?) ~ '^-?[0-9]+([.][0-9]+)?$')::int as predios,
                    coalesce(sum((cache.atributos->>?)::numeric) filter (where (cache.atributos->>?) ~ '^-?[0-9]+([.][0-9]+)?$'), 0) as total,
                    coalesce(ST_Area(ST_Union(ST_Intersection(cache.geom, area.geom)) filter (where (cache.atributos->>?) ~ '^-?[0-9]+([.][0-9]+)?$')), 0) as area_m2
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
               and ST_Intersects(cache.geom, area.geom)",
            [$campo, $campo, $campo, $campo, $analisis->id, $fila['id'], $numero, $numero],
        );
        $predios = (int) $suma->predios;
        $total = (float) $suma->total;
        $hectareas = ((float) $suma->area_m2) / 10000;

        return [
            'incluido' => true,
            'campo' => $campo,
            'predios' => $predios,
            'total' => $this->numero($total),
            'por_predio' => $predios > 0 ? $this->numero($total / $predios) : null,
            'por_ha' => $hectareas > 0 ? $this->numero($total / $hectareas) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function campoAvaluo(array $atributos): ?string
    {
        $presentes = [];

        foreach ($atributos as $clave => $valor) {
            if (is_string($clave)) {
                $presentes[strtoupper($clave)] = $clave;
            }
        }

        foreach (self::CAMPOS_AVALUO as $campo) {
            if (isset($presentes[$campo])) {
                return $presentes[$campo];
            }
        }

        return null;
    }

    /**
     * @return array{total: float, analizada: float}
     */
    private function areas(AnalisisArea $analisis): array
    {
        $fila = DB::selectOne(
            'select ST_Area(coalesce(geometria_original, geom)) / 10000 as total,
                    ST_Area(geom) / 10000 as analizada
             from inteligencia.analisis_area where id = ?',
            [$analisis->id],
        );

        return [
            'total' => (float) ($fila->total ?? 0),
            'analizada' => (float) ($fila->analizada ?? 0),
        ];
    }

    /**
     * @return list<string>
     */
    private function municipios(AnalisisArea $analisis): array
    {
        $hayGeometrias = (int) DB::table('suelos.limite_municipio')->count() > 0;

        if ($hayGeometrias) {
            $filas = DB::select(
                'select m.codigo_dane, m.nombre
                 from suelos.limite_municipio lm
                 join municipios m on m.id = lm.municipio_id
                 join inteligencia.analisis_area area on area.id = ?
                 where ST_Intersects(ST_Transform(lm.geom, 9377), area.geom)
                 order by m.codigo_dane',
                [$analisis->id],
            );

            return array_map(
                fn (object $fila): string => $fila->codigo_dane.' — '.$fila->nombre,
                $filas,
            );
        }

        return $this->municipiosDane($analisis);
    }

    /**
     * @return list<string>
     */
    private function municipiosDane(AnalisisArea $analisis): array
    {
        $geojson = json_decode((string) $analisis->geoJson4326(), true);
        $anillos = $this->anillos($geojson);
        $rings = array_map(
            fn (array $anillo): array => array_map(fn (array $punto): array => [$punto[0], $punto[1]], $anillo),
            $anillos,
        );

        if ($rings === []) {
            return [];
        }

        try {
            $respuesta = Http::connectTimeout(min(15, max(1, (int) config('inteligencia.http.connect_timeout', 30))))
                ->timeout(30)
                ->asForm()
                ->post(rtrim((string) config('inteligencia.municipios_dane_url'), '/').'/query', [
                    'where' => "DPTO_CCDGO='50'",
                    'geometry' => json_encode([
                        'rings' => $rings,
                        'spatialReference' => ['wkid' => 4326],
                    ], JSON_THROW_ON_ERROR),
                    'geometryType' => 'esriGeometryPolygon',
                    'inSR' => '4326',
                    'spatialRel' => 'esriSpatialRelIntersects',
                    'outFields' => 'MPIO_CDPMP,MPIO_CNMBRE',
                    'returnGeometry' => 'false',
                    'f' => 'json',
                ])
                ->throw()
                ->json();
        } catch (Throwable) {
            return ['No se pudo consultar el límite municipal del DANE (MGN 2025).'];
        }

        $nombres = [];

        foreach (is_array($respuesta['features'] ?? null) ? $respuesta['features'] : [] as $feature) {
            $atributos = is_array($feature['attributes'] ?? null) ? $feature['attributes'] : [];
            $codigo = $atributos['MPIO_CDPMP'] ?? null;
            $nombre = $atributos['MPIO_CNMBRE'] ?? null;

            if (is_scalar($codigo) && is_scalar($nombre) && (string) $codigo !== '' && (string) $nombre !== '') {
                $nombres[(string) $codigo] = (string) $codigo.' — '.(string) $nombre;
            }
        }

        ksort($nombres);

        return $nombres === []
            ? ['Ningún municipio del MGN 2025 intersecta el polígono.']
            : array_values($nombres);
    }

    /**
     * @param  list<array<string, mixed>>  $capas
     * @return list<array{titulo: string, texto: string}>
     */
    private function alertas(array $capas): array
    {
        $porCodigo = collect($capas)->keyBy('codigo');
        $grupos = [
            'Títulos mineros' => 'ANM_TITULOS_SOLICITUDES',
            'Áreas protegidas' => 'RUNAP_AREAS_PROTEGIDAS',
            'Territorios étnicos' => 'ANT_RESGUARDOS_CONSEJOS',
            'Amenaza' => 'SGC_AMENAZA_MOVIMIENTOS_MASA',
            'Restricción de suelo' => 'CORMACARENA_POT_CLASIFICACION',
        ];
        $alertas = [];

        foreach ($grupos as $titulo => $codigo) {
            $capa = $porCodigo->get($codigo);

            if (! is_array($capa)) {
                $alertas[] = ['titulo' => $titulo, 'texto' => 'Sin capa en el catálogo'];

                continue;
            }

            if ($capa['disponible'] === false) {
                $alertas[] = ['titulo' => $titulo, 'texto' => 'Sin información (fuente no disponible)'];

                continue;
            }

            if ($codigo === 'CORMACARENA_POT_CLASIFICACION') {
                $restricciones = array_values(array_filter(
                    $capa['clases'] ?? [],
                    fn (array $clase): bool => $clase['restriccion'] === true,
                ));
                $alertas[] = [
                    'titulo' => $titulo,
                    'texto' => $restricciones === []
                        ? 'Sin cruce'
                        : implode('; ', array_map(
                            fn (array $clase): string => $clase['clase'].': '.$clase['ha'].' ha ('.$clase['porcentaje'].' %)',
                            $restricciones,
                        )),
                ];

                continue;
            }

            $conteo = (int) $capa['conteo'];
            $alertas[] = [
                'titulo' => $titulo,
                'texto' => $conteo > 0
                    ? $conteo.' entidades'.($capa['area_ha'] !== null ? ', '.$capa['area_ha'].' ha' : '')
                    : 'Sin cruce',
            ];
        }

        return $alertas;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function mapa(AnalisisArea $analisis, array $filas): string
    {
        $cruces = [];

        foreach ($filas as $indice => $fila) {
            if (($fila['pendiente'] ?? true) || (int) ($fila['conteo'] ?? 0) < 1) {
                continue;
            }

            $cruces[] = [
                'color' => InformeAnalisis::COLORES[$indice % count(InformeAnalisis::COLORES)],
                'geojson' => $this->geojsonCruce($analisis, (int) $fila['id']),
            ];
        }

        return $this->mapaBase->html($analisis, $cruces);
    }

    private function geojson(string $sql, array $parametros): ?string
    {
        try {
            $fila = DB::selectOne($sql, $parametros);
        } catch (Throwable) {
            return null;
        }

        $geojson = $fila?->geojson;

        return is_string($geojson) ? $geojson : null;
    }

    private function geojsonCruce(AnalisisArea $analisis, int $capaId): ?string
    {
        $numero = AnalisisResultado::query()
            ->where('analisis_area_id', $analisis->id)
            ->where('capa_id', $capaId)
            ->with('version')
            ->first()
            ?->version
            ?->numero;

        if ($numero === null) {
            return null;
        }

        return $this->geojson(
            'select ST_AsGeoJSON(ST_Transform(ST_SimplifyPreserveTopology(ST_UnaryUnion(ST_Collect(ST_Intersection(cache.geom, area.geom))), 200), 4326)) as geojson
             from inteligencia.capa_objeto_cache cache
             join inteligencia.analisis_area area on area.id = ?
             where cache.capa_id = ?
               and cache.valid_from <= ?
               and (cache.valid_to is null or cache.valid_to > ?)
               and ST_Intersects(cache.geom, area.geom)',
            [$analisis->id, $capaId, $numero, $numero],
        );
    }

    /**
     * @return list<list<array{0: float, 1: float}>>
     */
    private function anillos(mixed $geojson): array
    {
        if (is_string($geojson)) {
            $geojson = json_decode($geojson, true);
        }

        if (! is_array($geojson)) {
            return [];
        }

        $tipo = $geojson['type'] ?? null;

        if ($tipo === 'FeatureCollection') {
            $anillos = [];

            foreach ($geojson['features'] ?? [] as $feature) {
                array_push($anillos, ...$this->anillos($feature));
            }

            return $anillos;
        }

        if ($tipo === 'Feature') {
            return $this->anillos($geojson['geometry'] ?? null);
        }

        $coordenadas = $geojson['coordinates'] ?? null;

        if (! is_array($coordenadas)) {
            return [];
        }

        return match ($tipo) {
            'Polygon' => $this->anillo($coordenadas[0] ?? []),
            'MultiPolygon' => $this->unirAnillos(array_map(fn (array $poligono): array => $this->anillo($poligono[0] ?? []), $coordenadas)),
            'LineString' => $this->anillo($coordenadas),
            'MultiLineString' => $this->unirAnillos(array_map(fn (array $linea): array => $this->anillo($linea), $coordenadas)),
            'Point' => $this->anillo([
                [$coordenadas[0], $coordenadas[1]],
                [$coordenadas[0] + 0.001, $coordenadas[1]],
                [$coordenadas[0] + 0.001, $coordenadas[1] + 0.001],
                [$coordenadas[0], $coordenadas[1] + 0.001],
                [$coordenadas[0], $coordenadas[1]],
            ]),
            default => [],
        };
    }

    /**
     * @param  list<list<list<array{0: float, 1: float}>>>  $grupos
     * @return list<list<array{0: float, 1: float}>>
     */
    private function unirAnillos(array $grupos): array
    {
        $anillos = [];

        foreach ($grupos as $grupo) {
            array_push($anillos, ...$grupo);
        }

        return $anillos;
    }

    /**
     * @param  list<mixed>  $coordenadas
     * @return list<list<array{0: float, 1: float}>>
     */
    private function anillo(array $coordenadas): array
    {
        $anillo = [];

        foreach ($coordenadas as $punto) {
            if (is_array($punto) && is_numeric($punto[0] ?? null) && is_numeric($punto[1] ?? null)) {
                $anillo[] = [(float) $punto[0], (float) $punto[1]];
            }
        }

        return count($anillo) >= 2 ? [$anillo] : [];
    }

    private function numero(float $valor, int $decimales = 2): string
    {
        return number_format($valor, $decimales, ',', '.');
    }
}
