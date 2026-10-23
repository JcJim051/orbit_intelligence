<?php

namespace App\Filament\Pages;

use App\Models\PasivaRubro;
use App\Services\Analitica\EsquemaAnalitica;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class CargarPasiva extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    protected static ?string $navigationLabel = 'Cargar pasiva';

    protected static ?string $title = 'Pasiva de Hacienda';

    protected static ?string $slug = 'cargar-pasiva';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.cargar-pasiva';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('import', PasivaRubro::class) ?? false;
    }

    public function detalleAction(): Action
    {
        return Action::make('detalle')
            ->modalHeading(fn (array $arguments): string => 'Rubros de '.$arguments['grupo'])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (array $arguments) => view('filament.micrositio.detalle-conteo', [
                'columnas' => ['Fuente', 'Sector', 'Rubros', 'Definitiva'],
                'filas' => $this->filas((string) $arguments['grupo']),
            ]));
    }

    public function abrirDetalle(string $grupo, int $rubros): void
    {
        if ($rubros < 1) {
            return;
        }

        $this->mountAction('detalle', ['grupo' => $grupo]);
    }

    /**
     * @return list<list<string>>
     */
    private function filas(string $grupo): array
    {
        return DB::table(EsquemaAnalitica::tabla('v_pasiva_fuente_sector'))
            ->where('grupo_fuente', $grupo)
            ->orderBy('fuente_codigo')
            ->get()
            ->map(fn (object $fila): array => [
                (string) $fila->fuente,
                (string) $fila->sector,
                (string) $fila->rubros,
                number_format((float) $fila->definitiva, 0, ',', '.'),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $grupos = DB::table(EsquemaAnalitica::tabla('v_pasiva_por_grupo_fuente'))
            ->orderBy('grupo_fuente')
            ->get();

        return [
            'grupos' => $grupos,
            'corte' => PasivaRubro::query()->value('corte'),
        ];
    }
}
