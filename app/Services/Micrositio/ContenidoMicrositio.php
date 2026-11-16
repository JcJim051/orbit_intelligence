<?php

namespace App\Services\Micrositio;

use App\Models\GeoViewerPublication;
use App\Models\PublicToolPublication;
use App\Services\Micrositio\Mapa\ConfiguracionMapaInicio;
use Illuminate\Support\Facades\Storage;

class ContenidoMicrositio
{
    private const LEAD = 'Datos abiertos, indicadores y estudios que convierten la información del Meta en evidencia para planear, decidir y hacer seguimiento a la gestión pública.';

    private const FUENTES = 'Fuentes: Sistema Integral de Información Departamental (SIID) · SSPP · DANE · MinEducación. Corte: 13/11/2026.';

    public function __construct(
        private readonly PublicadorMicrositio $publicador,
        private readonly ConfiguracionMapaInicio $mapa,
        private readonly ContadoresMicrositio $contadores,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(bool $preview): array
    {
        $leer = $preview
            ? fn (string $seccion): array => $this->publicador->registrosBorrador($seccion)
            : fn (string $seccion): array => $this->publicador->registrosPublicados($seccion);

        $hero = $leer('hero')[0]['atributos'] ?? [];
        $pie = $leer('pie')[0]['atributos'] ?? [];
        $cifras = array_map(fn (array $registro): array => $this->cifra($registro['atributos']), $leer('cifras'));
        $contadores = array_values(array_filter($cifras, fn (array $cifra): bool => $cifra['es_contador']));
        $presupuestales = array_values(array_filter($cifras, fn (array $cifra): bool => ! $cifra['es_contador']));
        $heroNuevo = array_key_exists('eyebrow', $hero);

        return [
            'preview' => $preview,
            'has_geovisors' => GeoViewerPublication::query()->where('visible', true)->whereHas('viewer', fn ($query) => $query->where('status', 'published'))->exists(),
            'public_analysis_active' => PublicToolPublication::query()->where('codigo', PublicToolPublication::GEOSPATIAL_ANALYSIS)->where('visible', true)->exists(),
            'hero' => [
                'eyebrow' => $this->texto($hero['eyebrow'] ?? null, 'Sistema Integral de Información Departamental'),
                'titulo' => (string) ($hero['titulo'] ?? ''),
                'subtitulo' => $heroNuevo ? $this->texto($hero['subtitulo'] ?? null, self::LEAD) : self::LEAD,
                'fuentes_linea' => $this->texto($hero['fuentes_linea'] ?? null, self::FUENTES),
                'usar_imagen_fondo' => (bool) ($hero['usar_imagen_fondo'] ?? false),
                'banner_url' => $this->urlBanner($preview),
                'pildoras' => $this->pildoras(is_array($hero['pildoras'] ?? null) ? $hero['pildoras'] : []),
            ],
            'contadores' => $contadores !== [] ? $contadores : $this->contadoresDeRespaldo(),
            'cifras' => array_map(fn (array $cifra): array => [
                'valor' => $cifra['valor'],
                'descripcion' => $cifra['descripcion'],
                'fuente' => $cifra['fuente'],
                'corte' => $cifra['corte'],
                'es_ejemplo' => $cifra['es_ejemplo'],
            ], $presupuestales),
            'temas' => array_map(fn (array $registro): array => [
                'slug' => (string) $registro['atributos']['slug'],
                'nombre' => (string) $registro['atributos']['nombre'],
            ], $leer('temas')),
            'indicadores' => array_map(fn (array $registro): array => [
                'id' => $registro['id'],
                'codigo' => (string) $registro['atributos']['codigo'],
                'nombre' => (string) $registro['atributos']['nombre'],
                'etiqueta' => $registro['atributos']['codigo'].' — '.$registro['atributos']['nombre'],
                'tema' => (string) ($registro['atributos']['tema_slug'] ?? ''),
                'descripcion' => (string) $registro['atributos']['descripcion'],
                'es_publico' => (bool) $registro['atributos']['es_publico'],
                'es_ejemplo' => (bool) $registro['atributos']['es_ejemplo'],
                'dashboard_uuid' => $registro['atributos']['dashboard_uuid'] ?: null,
                'conjunto_datos' => $registro['atributos']['conjunto_datos'] ?: null,
                'tabular_data_source_id' => $registro['atributos']['tabular_data_source_id'] ?: null,
                'tabular_data_source_version' => $registro['atributos']['tabular_data_source_version'] ?: null,
            ], $leer('indicadores')),
            'publicaciones' => array_map(fn (array $registro): array => $this->publicacion($registro, $preview), $leer('publicaciones')),
            'menu' => array_map(fn (array $registro): array => [
                'texto' => (string) $registro['atributos']['texto'],
                'url' => (string) $registro['atributos']['url'],
            ], $leer('menu')),
            'mapa' => $this->mapa->armar($preview),
            'contacto' => [
                'direccion' => (string) ($pie['direccion'] ?? ''),
                'horario' => (string) ($pie['horario'] ?? ''),
                'conmutador' => (string) ($pie['conmutador'] ?? ''),
                'linea_gratuita' => (string) ($pie['linea_gratuita'] ?? ''),
                'anticorrupcion' => (string) ($pie['anticorrupcion'] ?? ''),
                'correo' => (string) ($pie['correo'] ?? ''),
                'judiciales' => (string) ($pie['judiciales'] ?? ''),
            ],
        ];
    }

    public function urlBanner(bool $preview): ?string
    {
        $registros = $preview
            ? $this->publicador->registrosBorrador('hero')
            : $this->publicador->registrosPublicados('hero');
        $path = $registros[0]['atributos']['banner_path'] ?? null;

        if (! is_string($path) || $path === '' || ! $this->rutaSegura($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return route('micrositio.banner', ['preview' => $preview ? 1 : null]);
    }

    public function rutaSegura(string $path): bool
    {
        return str_starts_with($path, 'micrositio/')
            && ! str_contains($path, '..')
            && ! str_contains($path, '\\');
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function cifra(array $atributos): array
    {
        $origen = (string) ($atributos['origen'] ?? 'manual');
        $criterio = (string) ($atributos['criterio'] ?? 'activos');
        $esContador = in_array($origen, ContadoresMicrositio::ORIGENES, true);
        $calculado = $esContador ? $this->contadores->valor($origen, $criterio) : null;
        $valor = $calculado === null ? (string) ($atributos['valor'] ?? '') : (string) $calculado;

        return [
            'valor' => (string) ($atributos['valor'] ?? ''),
            'valor_mostrado' => $valor,
            'es_contador' => $esContador,
            'es_dinamico' => $esContador && $calculado !== null,
            'origen' => $origen !== '' ? $origen : 'manual',
            'criterio' => $criterio !== '' ? $criterio : 'activos',
            'etiqueta' => $this->texto($atributos['etiqueta'] ?? null, (string) ($atributos['descripcion'] ?? '')),
            'descripcion' => (string) ($atributos['descripcion'] ?? ''),
            'fuente' => (string) ($atributos['fuente'] ?? ''),
            'corte' => $this->fecha((string) ($atributos['fecha_corte'] ?? '')),
            'es_ejemplo' => (bool) ($atributos['es_ejemplo'] ?? false),
            'clicable' => $esContador,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contadoresDeRespaldo(): array
    {
        return array_map(fn (array $atributos): array => $this->cifra($atributos), [
            ['valor' => '203', 'origen' => 'indicadores_sectoriales', 'criterio' => 'activos', 'etiqueta' => 'Indicadores Sectoriales', 'descripcion' => 'Indicadores sectoriales activos del listado maestro'],
            ['valor' => '154', 'origen' => 'indicadores_territoriales', 'criterio' => 'activos', 'etiqueta' => 'Indicadores Territoriales (Propios)', 'descripcion' => 'Indicadores territoriales propios activos'],
            ['valor' => '18', 'origen' => 'politicas_publicas', 'criterio' => 'activos', 'etiqueta' => 'Políticas Públicas', 'descripcion' => 'Políticas públicas publicadas, sin ejemplos'],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $pildoras
     * @return list<array{texto: string, url: string, primaria: bool}>
     */
    private function pildoras(array $pildoras): array
    {
        $validas = [];

        foreach ($pildoras as $pildora) {
            if (! is_array($pildora) || ! filled($pildora['texto'] ?? null) || ! filled($pildora['url'] ?? null)) {
                continue;
            }

            $validas[] = [
                'texto' => (string) $pildora['texto'],
                'url' => (string) $pildora['url'],
                'primaria' => false,
            ];
        }

        if ($validas === []) {
            $validas = [
                ['texto' => 'Indicadores', 'url' => route('micrositio.indicadores', [], false), 'primaria' => false],
                ['texto' => 'Políticas públicas', 'url' => route('micrositio.politicas', [], false), 'primaria' => false],
                ['texto' => 'Publicaciones', 'url' => route('micrositio.inicio', [], false).'#publicaciones', 'primaria' => false],
                ['texto' => 'Visor geográfico', 'url' => route('micrositio.geovisores.index', [], false), 'primaria' => false],
            ];
        }

        if (isset($validas[0])) {
            $validas[0]['primaria'] = true;
        }

        return array_slice($validas, 0, 5);
    }

    private function texto(mixed $valor, string $defecto): string
    {
        return is_string($valor) && trim($valor) !== '' ? $valor : $defecto;
    }

    private function fecha(string $fecha): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            return $fecha;
        }

        return $partes[3].'/'.$partes[2].'/'.$partes[1];
    }

    /** @return array<string, mixed> */
    private function publicacion(array $registro, bool $preview): array
    {
        $atributos = $registro['atributos'];
        $path = $atributos['archivo_path'] ?? null;
        $disponible = is_string($path) && str_starts_with($path, 'micrositio/publicaciones/')
            && $this->rutaSegura($path) && Storage::disk('local')->exists($path);
        $nombre = basename((string) ($atributos['nombre_archivo'] ?? $path ?? ''));
        $formato = strtoupper(pathinfo($nombre, PATHINFO_EXTENSION));
        $bytes = $disponible ? Storage::disk('local')->size($path) : null;

        return [
            'id' => $registro['id'],
            'tipo' => (string) $atributos['tipo'],
            'titulo' => (string) $atributos['titulo'],
            'fecha' => (string) ($atributos['fecha'] ?? ''),
            'resumen' => (string) $atributos['resumen'],
            'es_ejemplo' => (bool) $atributos['es_ejemplo'],
            'formato' => $formato ?: 'DOC',
            'peso' => $bytes === null ? null : ($bytes >= 1048576
                ? number_format($bytes / 1048576, 1, ',', '.').' MB'
                : number_format($bytes / 1024, 1, ',', '.').' KB'),
            'archivo_url' => $disponible ? route('micrositio.publicaciones.archivo', ['publicacion' => $registro['id'], 'preview' => $preview ? 1 : null]) : null,
            'vista_previa_url' => $disponible && $formato === 'PDF'
                ? route('micrositio.publicaciones.archivo', ['publicacion' => $registro['id'], 'preview' => $preview ? 1 : null, 'inline' => 1]) : null,
        ];
    }
}
