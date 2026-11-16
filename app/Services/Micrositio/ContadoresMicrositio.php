<?php

namespace App\Services\Micrositio;

use App\Models\IndicadorTerritorial;
use App\Models\SiidIndicador;
use App\Models\SiidPublicacion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ContadoresMicrositio
{
    /** @var list<string> */
    public const ORIGENES = [
        'indicadores_sectoriales',
        'indicadores_territoriales',
        'politicas_publicas',
    ];

    /** @var list<string> */
    public const CRITERIOS = [
        'activos',
        'publicados_micrositio',
    ];

    public function __construct(private readonly PublicadorMicrositio $publicador) {}

    public function valor(string $origen, string $criterio): ?int
    {
        if (! in_array($origen, self::ORIGENES, true)) {
            return null;
        }

        try {
            return Cache::remember(
                $this->clave('contador', $origen, $criterio),
                now()->addMinutes(10),
                fn (): int => $this->contar($origen, $criterio),
            );
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return list<array{grupo: string, total: int, items: list<array<string, mixed>>}>
     */
    public function desglose(string $origen, string $criterio): array
    {
        if (! in_array($origen, self::ORIGENES, true)) {
            return [];
        }

        try {
            return Cache::remember(
                $this->clave('desglose', $origen, $criterio),
                now()->addMinutes(10),
                fn (): array => $this->agrupar($origen, $criterio),
            );
        } catch (Throwable) {
            return [];
        }
    }

    public static function olvidar(): void
    {
        foreach (self::ORIGENES as $origen) {
            foreach (self::CRITERIOS as $criterio) {
                Cache::forget("micrositio:contador:{$origen}:{$criterio}");
                Cache::forget("micrositio:desglose:{$origen}:{$criterio}");
            }
        }
    }

    private function contar(string $origen, string $criterio): int
    {
        return match ($origen) {
            'indicadores_sectoriales' => $this->sectoriales($criterio)->count(),
            'indicadores_territoriales' => $this->territoriales($criterio)->count(),
            'politicas_publicas' => count($this->politicas()),
            default => 0,
        };
    }

    /**
     * @return list<array{grupo: string, total: int, items: list<array<string, mixed>>}>
     */
    private function agrupar(string $origen, string $criterio): array
    {
        return match ($origen) {
            'indicadores_sectoriales' => $this->gruposSectoriales($criterio),
            'indicadores_territoriales' => $this->gruposTerritoriales($criterio),
            'politicas_publicas' => $this->gruposPoliticas(),
            default => [],
        };
    }

    /**
     * @return Builder<SiidIndicador>
     */
    private function sectoriales(string $criterio): Builder
    {
        $consulta = SiidIndicador::query()->where('activo', true);

        if ($criterio === 'publicados_micrositio') {
            $consulta->whereHas('publicacion', fn (Builder $publicacion) => $publicacion->where('visible', true));
        }

        return $consulta;
    }

    /**
     * @return Builder<IndicadorTerritorial>
     */
    private function territoriales(string $criterio): Builder
    {
        $consulta = IndicadorTerritorial::query()->where('activo', true);

        if ($criterio === 'publicados_micrositio') {
            $consulta->whereHas('publicacion', fn (Builder $publicacion) => $publicacion->where('visible', true));
        }

        return $consulta;
    }

    /**
     * Políticas publicadas que no están marcadas como ejemplo.
     *
     * @return list<array{id: int, atributos: array<string, mixed>}>
     */
    private function politicas(): array
    {
        return array_values(array_filter(
            $this->publicador->registrosPublicados('politicas'),
            fn (array $registro): bool => ! (bool) ($registro['atributos']['es_ejemplo'] ?? false),
        ));
    }

    /**
     * @return list<array{grupo: string, total: int, items: list<array{etiqueta: string, url: ?string}>}>
     */
    private function gruposSectoriales(string $criterio): array
    {
        $grupos = [];

        $indicadores = $this->sectoriales($criterio)
            ->with(['eje', 'area', 'publicacion'])
            ->orderBy('codigo')
            ->get();

        foreach ($indicadores as $indicador) {
            $grupo = ($indicador->eje?->nombre ?: 'Por clasificar').' — '.($indicador->area?->nombre ?: 'Por clasificar');
            $grupos[$grupo][] = [
                'etiqueta' => $indicador->etiqueta(),
                'url' => $this->urlPublicacion($indicador->publicacion),
            ];
        }

        return $this->empaquetar($grupos);
    }

    /**
     * @return list<array{grupo: string, total: int, items: list<array{etiqueta: string, url: ?string}>}>
     */
    private function gruposTerritoriales(string $criterio): array
    {
        $grupos = [];

        $indicadores = $this->territoriales($criterio)
            ->with('publicacion')
            ->orderBy('codigo')
            ->get();

        foreach ($indicadores as $indicador) {
            $grupo = filled($indicador->area_tematica) ? (string) $indicador->area_tematica : 'Por clasificar';
            $grupos[$grupo][] = [
                'etiqueta' => $indicador->etiqueta(),
                'url' => $this->urlPublicacion($indicador->publicacion),
            ];
        }

        return $this->empaquetar($grupos);
    }

    /**
     * @return list<array{grupo: string, total: int, items: list<array{etiqueta: string, url: string, vigencia: string}>}>
     */
    private function gruposPoliticas(): array
    {
        $items = [];

        foreach ($this->politicas() as $registro) {
            $atributos = $registro['atributos'];
            $nombre = (string) ($atributos['nombre'] ?? '');
            $slug = (string) ($atributos['slug'] ?? '');
            $items[] = [
                'etiqueta' => $nombre,
                'vigencia' => (string) ($atributos['vigencia'] ?? ''),
                'url' => $slug !== '' ? route('micrositio.politicas', ['politica' => $slug]) : route('micrositio.politicas'),
            ];
        }

        if ($items === []) {
            return [];
        }

        return [[
            'grupo' => 'Políticas públicas',
            'total' => count($items),
            'items' => $items,
        ]];
    }

    private function urlPublicacion(?SiidPublicacion $publicacion): ?string
    {
        if ($publicacion === null || ! $publicacion->visible) {
            return null;
        }

        return route('micrositio.siid.show', $publicacion);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grupos
     * @return list<array{grupo: string, total: int, items: list<array<string, mixed>>}>
     */
    private function empaquetar(array $grupos): array
    {
        $salida = [];

        foreach ($grupos as $grupo => $items) {
            $salida[] = [
                'grupo' => $grupo,
                'total' => count($items),
                'items' => $items,
            ];
        }

        return $salida;
    }

    private function clave(string $tipo, string $origen, string $criterio): string
    {
        $criterio = in_array($criterio, self::CRITERIOS, true) ? $criterio : 'activos';

        return "micrositio:{$tipo}:{$origen}:{$criterio}";
    }
}
