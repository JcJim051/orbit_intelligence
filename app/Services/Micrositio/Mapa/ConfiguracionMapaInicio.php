<?php

namespace App\Services\Micrositio\Mapa;

use App\Contracts\Micrositio\FuenteDatosMapaInicio;
use App\Enums\GeoViewerStatus;
use App\Models\GeoViewer;
use App\Services\Micrositio\PublicadorMicrositio;
use Throwable;

class ConfiguracionMapaInicio
{
    /** @var array<string, string> */
    public const ROTULOS = [
        'POB-2026' => 'Población 2026',
        'PETROLEO' => 'Petróleo',
        'AGRO-PROD' => 'Producción agrícola',
    ];

    public function __construct(
        private readonly PublicadorMicrositio $publicador,
        private readonly FuenteDatosMapaInicio $fuente,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function armar(bool $preview): array
    {
        $registros = $preview
            ? $this->publicador->registrosBorrador('mapa')
            : $this->publicador->registrosPublicados('mapa');
        $atributos = $registros[0]['atributos'] ?? [];
        $hayConfiguracion = $atributos !== [];
        $disponibles = collect($this->fuente->capas())->pluck('codigo')->filter()->values()->all();
        $principal = (string) ($atributos['capa_principal'] ?? 'POB-2026');
        if (! in_array($principal, $disponibles, true)) {
            $principal = in_array('POB-2026', $disponibles, true) ? 'POB-2026' : (string) ($disponibles[0] ?? 'POB-2026');
        }
        $adicionales = array_values(array_filter(
            (array) ($atributos['capas_adicionales'] ?? []),
            fn (mixed $codigo): bool => is_string($codigo) && $codigo !== '' && $codigo !== $principal,
        ));

        $adicionales = array_values(array_filter(
            $adicionales,
            fn (string $codigo): bool => in_array($codigo, $disponibles, true),
        ));

        if (! $hayConfiguracion || $adicionales === []) {
            $adicionales = array_values(array_filter(
                ['PETROLEO', 'AGRO-PROD'],
                fn (string $codigo): bool => in_array($codigo, $disponibles, true) && $codigo !== $principal,
            ));
        }

        $codigos = [];

        foreach (array_merge([$principal], array_slice($adicionales, 0, 3)) as $codigo) {
            if (! in_array($codigo, $codigos, true)) {
                $codigos[] = $codigo;
            }
        }

        $capas = [];

        foreach ($codigos as $codigo) {
            $capa = $this->fuente->capa($codigo);

            if ($capa !== null) {
                $capas[] = $this->capaPublica($capa);
            }
        }

        $url = trim((string) ($atributos['url_cta'] ?? ''));

        return [
            'version' => (string) ($this->fuente->metadatos()['version'] ?? 'bootstrap'),
            'mostrar_bloque' => (bool) ($atributos['mostrar_bloque'] ?? true),
            'capa_principal' => $principal,
            'codigos' => $codigos,
            'capas' => $capas,
            'paleta' => (string) ($atributos['paleta'] ?? 'auto'),
            'polaridad' => (string) ($atributos['polaridad'] ?? 'auto'),
            'periodo_modo' => (string) ($atributos['periodo_modo'] ?? 'ultimo'),
            'periodo_fijo' => $atributos['periodo_fijo'] ?? null,
            'texto_tarjeta' => $this->texto($atributos, 'texto_tarjeta', '29 municipios · capital Villavicencio'),
            'texto_cta' => $this->texto($atributos, 'texto_cta', 'Explorar en el visor'),
            'url_cta' => $url !== '' ? $url : route('micrositio.geovisores.index'),
            'url_ficha_tecnica' => $atributos['url_ficha_tecnica'] ?? null,
            'url_indicadores_municipio' => $atributos['url_indicadores_municipio'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $capa
     * @return array<string, mixed>
     */
    public function capaPublica(array $capa): array
    {
        $codigo = (string) $capa['codigo'];
        $capa['rotulo_corto'] = (string) ($capa['rotulo_corto'] ?? self::ROTULOS[$codigo] ?? $capa['nombre']);

        return $capa;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function texto(array $atributos, string $clave, string $defecto): string
    {
        $valor = $atributos[$clave] ?? null;

        return is_string($valor) && trim($valor) !== '' ? $valor : $defecto;
    }

    public function hayGeovisores(): bool
    {
        try {
            return GeoViewer::query()->where('status', GeoViewerStatus::Published)->exists();
        } catch (Throwable) {
            return false;
        }
    }

    public function urlVisor(): string
    {
        try {
            $visor = GeoViewer::query()->where('status', GeoViewerStatus::Published)->orderBy('name')->first();

            if ($visor !== null) {
                return route('geo-viewers.embed', $visor);
            }
        } catch (Throwable) {
            return '#';
        }

        return '#';
    }
}
