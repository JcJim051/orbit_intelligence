<?php

namespace App\Filament\Pages;

use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Services\InteligenciaGeografica\InformeAnalisis;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

class VisorCapas extends Page
{
    protected static ?string $navigationLabel = 'Visor de capas';

    protected static ?string $title = 'Visor de capas';

    protected static ?string $slug = 'visor-capas';

    protected static string|UnitEnum|null $navigationGroup = 'Inteligencia geográfica';

    protected static ?int $navigationSort = 75;

    protected string $view = 'filament.inteligencia.visor-capas';

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('viewAny', Capa::class) ?? false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function capasVisibles(): array
    {
        $capaActiva = request()->query('capa');

        return Capa::query()
            ->with(['fuente', 'versionVigente'])
            ->withCount('objetosCache')
            ->withExists([
                'sincronizaciones as tiene_sync_atribuible' => fn ($consulta) => $consulta->whereIn('estado', EstadoSincronizacionCapa::atribuibles()),
            ])
            ->where('activa', true)
            ->orderBy('codigo')
            ->get()
            ->values()
            ->map(function (Capa $capa, int $indice) use ($capaActiva): array {
                return [
                    'id' => $capa->id,
                    'etiqueta' => $capa->etiqueta(),
                    'color' => InformeAnalisis::COLORES[$indice % count(InformeAnalisis::COLORES)],
                    'fuente' => $capa->fuente?->etiqueta(),
                    'frescura' => $capa->estado_frescura->label(),
                    'ultima' => $capa->fecha_ultima_sincronizacion?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'Sin actualización',
                    'ttl' => $capa->ttl_horas,
                    'proxima' => $capa->textoProximaCadencia(),
                    'aviso' => $capa->fecha_ultima_sincronizacion === null ? Capa::AVISO_CONECTOR : null,
                    'alerta' => $capa->textoAlerta(),
                    'version' => $capa->versionVigente?->numero,
                    'pendiente' => $capa->objetos_cache_count === 0 && ! $capa->tiene_sync_atribuible,
                    'activa' => (string) $capaActiva === (string) $capa->id,
                    'url' => route('inteligencia.capas.geojson', $capa),
                ];
            })
            ->all();
    }

    protected function getViewData(): array
    {
        return [
            'capas' => $this->capasVisibles(),
        ];
    }
}
