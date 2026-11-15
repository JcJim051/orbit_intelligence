<?php

namespace App\Services\Micrositio;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use App\Models\MicrositioTextoInicio;
use App\Services\Micrositio\Mapa\ConfiguracionMapaInicio;
use Illuminate\Support\Facades\Storage;

class ContenidoMicrositio
{
    public function __construct(
        private readonly PublicadorMicrositio $publicador,
        private readonly ContadoresMicrositio $contadores,
        private readonly ConfiguracionMapaInicio $mapa,
        private readonly FuenteDatosMapaInicio $fuente,
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
        $hayGeovisores = $this->mapa->hayGeovisores();

        return [
            'preview' => $preview,
            'has_geovisors' => $hayGeovisores,
            'hero' => [
                'eyebrow' => $this->texto($hero['eyebrow'] ?? null, 'Sistema Integral de Información Departamental'),
                'titulo' => (string) ($hero['titulo'] ?? ''),
                'subtitulo' => (string) ($hero['subtitulo'] ?? ''),
                'fuentes_linea' => $this->texto($hero['fuentes_linea'] ?? null, 'Fuentes: Sistema Integral de Información Departamental (SIID) · SSPP · DANE · MinEducación. Corte: 13/11/2026.'),
                'usar_imagen_fondo' => (bool) ($hero['usar_imagen_fondo'] ?? false),
                'banner_url' => $this->urlBanner($preview),
                'pildoras' => $this->pildoras(is_array($hero['pildoras'] ?? null) ? $hero['pildoras'] : [], $hayGeovisores),
            ],
            'cifras' => array_map(fn (array $registro): array => $this->cifra($registro['atributos']), $leer('cifras')),
            'mapa' => $this->mapa->armar($preview),
            'ultimos' => array_map(fn (array $registro): array => $this->ultimo($registro['atributos']), $leer('ultimos')),
            'textos' => $this->textos($leer('textos_inicio')[0]['atributos'] ?? []),
            'aliados' => array_map(fn (array $registro): array => [
                'id' => $registro['id'],
                'nombre' => (string) $registro['atributos']['nombre'],
                'url' => $registro['atributos']['url'] ?: null,
                'logo_url' => $this->urlLogo($registro, $preview),
            ], $leer('aliados')),
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
            'publicaciones' => array_map(fn (array $registro): array => [
                'id' => $registro['id'],
                'tipo' => (string) $registro['atributos']['tipo'],
                'titulo' => (string) $registro['atributos']['titulo'],
                'fecha' => (string) ($registro['atributos']['fecha'] ?? ''),
                'resumen' => (string) $registro['atributos']['resumen'],
                'es_ejemplo' => (bool) $registro['atributos']['es_ejemplo'],
                'archivo_url' => filled($registro['atributos']['archivo_path'] ?? null)
                    ? route('micrositio.publicaciones.archivo', ['publicacion' => $registro['id'], 'preview' => $preview ? 1 : null])
                    : null,
            ], $leer('publicaciones')),
            'menu' => array_map(fn (array $registro): array => [
                'texto' => (string) $registro['atributos']['texto'],
                'url' => (string) $registro['atributos']['url'],
            ], $leer('menu')),
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
        $esDinamico = $origen !== '' && $origen !== 'manual';
        $calculado = $esDinamico ? $this->contadores->valor($origen, $criterio) : null;
        $valor = $calculado === null ? (string) ($atributos['valor'] ?? '') : (string) $calculado;

        return [
            'valor' => (string) ($atributos['valor'] ?? ''),
            'valor_mostrado' => $valor,
            'es_dinamico' => $esDinamico && $calculado !== null,
            'origen' => $origen !== '' ? $origen : 'manual',
            'criterio' => $criterio !== '' ? $criterio : 'activos',
            'etiqueta' => $this->texto($atributos['etiqueta'] ?? null, (string) ($atributos['descripcion'] ?? '')),
            'descripcion' => (string) ($atributos['descripcion'] ?? ''),
            'fuente' => (string) ($atributos['fuente'] ?? ''),
            'corte' => $this->fecha((string) ($atributos['fecha_corte'] ?? '')),
            'es_ejemplo' => (bool) ($atributos['es_ejemplo'] ?? false),
            'clicable' => $esDinamico && $calculado !== null && $calculado > 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $pildoras
     * @return list<array{texto: string, url: string, primaria: bool}>
     */
    private function pildoras(array $pildoras, bool $hayGeovisores): array
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
            ];

            if ($hayGeovisores) {
                $validas[] = ['texto' => 'Visor geográfico', 'url' => $this->mapa->urlVisor(), 'primaria' => false];
            }
        }

        if (isset($validas[0])) {
            $validas[0]['primaria'] = true;
        }

        return array_slice($validas, 0, 5);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, mixed>
     */
    private function ultimo(array $atributos): array
    {
        $codigo = (string) ($atributos['codigo'] ?? '');
        $nombre = (string) ($atributos['nombre'] ?? '');
        $capaCodigo = is_string($atributos['capa_codigo'] ?? null) ? $atributos['capa_codigo'] : '';
        $capa = $capaCodigo !== '' ? $this->fuente->capa($capaCodigo) : null;
        $valor = (string) ($atributos['valor'] ?? '');
        $periodoManual = (string) ($atributos['periodo'] ?? '');
        $detalle = trim((string) ($atributos['unidad'] ?? '').($periodoManual !== '' ? ' · '.$periodoManual : ''));
        $deltaTexto = (string) ($atributos['delta_texto'] ?? '');
        $deltaTipo = (string) ($atributos['delta_tipo'] ?? 'neutro');
        $desdeCapa = false;

        if (is_array($capa) && ($capa['valor_departamental'] ?? null) !== null) {
            $desdeCapa = true;
            $periodo = (string) ($capa['periodo'] ?? '');
            $numero = $this->numero($capa, $capa['valor_departamental']);
            $valor = ($capa['unidad'] ?? '') === '%' ? $numero.' %' : $numero;
            $detalle = ($capa['unidad'] ?? '') === 'habitantes'
                ? 'habitantes · '.$periodo
                : ($codigo === 'NBI-2018' || $capaCodigo === 'NBI-2018' ? 'Meta · CNPV '.$periodo : 'Meta · año '.$periodo);
            $delta = $this->deltaDeCapa($capa);

            if ($delta !== null) {
                $deltaTexto = $delta['texto'];
                $deltaTipo = $delta['tipo'];
            }
        }

        return [
            'insignia' => $codigo,
            'titulo' => $codigo.' — '.$nombre,
            'descripcion' => (string) ($atributos['descripcion'] ?? ''),
            'valor' => $valor,
            'detalle' => $detalle,
            'delta_texto' => $deltaTexto,
            'delta_tipo' => $deltaTipo,
            'es_ejemplo' => (bool) ($atributos['es_ejemplo'] ?? false) && ! $desdeCapa,
            'url' => filled($atributos['url'] ?? null) ? (string) $atributos['url'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $capa
     * @return array{texto: string, tipo: string}|null
     */
    private function deltaDeCapa(array $capa): ?array
    {
        $actual = $capa['valor_departamental'] ?? null;
        $anterior = $capa['valor_anterior_departamental'] ?? null;

        if ($actual === null || $anterior === null) {
            if (str_starts_with((string) ($capa['codigo'] ?? ''), 'NBI')) {
                return ['texto' => 'Último censo', 'tipo' => 'neutro'];
            }

            return null;
        }

        $periodo = (string) ($capa['periodo_anterior'] ?? '');
        $actual = (float) $actual;
        $anterior = (float) $anterior;

        if (($capa['unidad'] ?? '') === 'habitantes') {
            $cambio = $anterior == 0.0 ? 0.0 : (($actual / $anterior) - 1) * 100;

            return [
                'texto' => ($cambio >= 0 ? '▲ +' : '▼ −').number_format(abs($cambio), 1, ',', '.').' % vs. '.$periodo,
                'tipo' => $cambio >= 0 ? 'sube_bueno' : 'baja_malo',
            ];
        }

        $diferencia = $actual - $anterior;
        $favorable = ($diferencia >= 0) !== (($capa['polaridad'] ?? '') === 'alto_malo');

        return [
            'texto' => ($diferencia >= 0 ? '▲ +' : '▼ −').number_format(abs($diferencia), (int) ($capa['decimales'] ?? 2), ',', '.').' p.p. vs. '.$periodo,
            'tipo' => $favorable ? 'sube_bueno' : 'baja_malo',
        ];
    }

    /**
     * @param  array<string, mixed>  $capa
     */
    private function numero(array $capa, mixed $valor): string
    {
        return number_format((float) $valor, (int) ($capa['decimales'] ?? 0), ',', '.');
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @return array<string, string>
     */
    private function textos(array $atributos): array
    {
        $salida = MicrositioTextoInicio::DEFECTO;

        foreach ($salida as $clave => $defecto) {
            if (filled($atributos[$clave] ?? null)) {
                $salida[$clave] = (string) $atributos[$clave];
            }
        }

        return $salida;
    }

    /**
     * @param  array{id: int, atributos: array<string, mixed>}  $registro
     */
    private function urlLogo(array $registro, bool $preview): ?string
    {
        $path = $registro['atributos']['logo_path'] ?? null;

        if (! is_string($path) || $path === '' || ! $this->rutaSegura($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        return route('micrositio.aliados.logo', ['aliado' => $registro['id'], 'preview' => $preview ? 1 : null]);
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
}
