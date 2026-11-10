<?php

namespace App\Filament\Pages;

use App\Models\Dependencia;
use App\Models\MicrositioIndicador;
use App\Services\Micrositio\PublicadorMicrositio;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

class TablerosSuperset extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    protected static ?string $navigationLabel = 'Tableros (Superset)';

    protected static ?string $title = 'Tableros de Superset';

    protected static ?string $slug = 'tableros-superset';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.tableros-superset';

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $usuario = auth()->user();
        $indicadores = MicrositioIndicador::query()
            ->with('tema')
            ->whereNotNull('dashboard_uuid')
            ->orderBy('orden')
            ->get();

        if (! $usuario?->isAdmin()) {
            $codigos = collect(app(PublicadorMicrositio::class)->registrosPublicados('indicadores'))
                ->filter(fn (array $registro): bool => filled($registro['atributos']['dashboard_uuid'] ?? null))
                ->map(fn (array $registro): string => (string) $registro['atributos']['codigo']);
            $indicadores = $indicadores->filter(fn (MicrositioIndicador $indicador): bool => $codigos->contains($indicador->codigo))->values();
        }

        $codigo = (string) request()->query('indicador', '');
        $seleccionado = $indicadores->firstWhere('codigo', $codigo) ?? $indicadores->first();

        return [
            'indicadores' => $indicadores,
            'seleccionado' => $seleccionado,
            'dependencias' => $usuario?->veTodosLosSectores()
                ? Dependencia::query()->where('activo', true)->orderBy('codigo')->get()
                : ($usuario?->dependencias()->orderBy('codigo')->get() ?? new Collection),
            'dependenciaId' => request()->integer('dependencia_id') ?: null,
            'rol' => config('superset.guest_role'),
            'configurado' => filled(config('superset.url')),
        ];
    }
}
