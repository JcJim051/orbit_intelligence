<?php

namespace App\Filament\Pages;

use App\Services\Analitica\DiccionarioAnalitica;
use App\Services\Analitica\EsquemaAnalitica;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

class CatalogoAnalitica extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    protected static ?string $navigationLabel = 'Catálogo de datos';

    protected static ?string $title = 'Catálogo para tableros';

    protected static ?string $slug = 'catalogo-analitica';

    protected static ?int $navigationSort = 80;

    protected string $view = 'filament.pages.catalogo-analitica';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function detalleAction(): Action
    {
        return Action::make('detalle')
            ->modalHeading(fn (array $arguments): string => (string) ($arguments['titulo'] ?? 'Detalle'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(fn (array $arguments) => view('filament.micrositio.detalle-conteo', [
                'columnas' => $arguments['columnas'] ?? [],
                'filas' => $arguments['filas'] ?? [],
            ]));
    }

    public function abrirColumnas(string $vista): void
    {
        $definicion = DiccionarioAnalitica::vista($vista);

        if ($definicion === null || count($definicion['columnas']) < 1) {
            return;
        }

        $this->mountAction('detalle', [
            'titulo' => $definicion['nombre'],
            'columnas' => ['Columna', 'Descripción', 'Fuente'],
            'filas' => array_map(fn (array $columna): array => [
                $columna['nombre'],
                $columna['descripcion'],
                $columna['fuente'],
            ], $definicion['columnas']),
        ]);
    }

    public function abrirFilas(string $vista, int $filas): void
    {
        $definicion = DiccionarioAnalitica::vista($vista);

        if ($definicion === null || $filas < 1) {
            return;
        }

        $nombres = array_slice(array_column($definicion['columnas'], 'nombre'), 0, 6);

        $registros = DB::table(EsquemaAnalitica::tabla($vista))
            ->limit(12)
            ->get()
            ->map(function (object $fila) use ($nombres): array {
                return array_map(
                    fn (string $nombre): string => mb_substr((string) ($fila->{$nombre} ?? ''), 0, 180),
                    $nombres,
                );
            })
            ->all();

        $this->mountAction('detalle', [
            'titulo' => 'Primeras filas de '.$vista,
            'columnas' => $nombres,
            'filas' => $registros,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $vistas = [];

        foreach (DiccionarioAnalitica::vistas() as $vista) {
            $vistas[] = [
                'nombre' => $vista['nombre'],
                'dominio' => $vista['dominio'],
                'descripcion' => $vista['descripcion'],
                'fuente' => $vista['fuente'],
                'corte' => $vista['corte'],
                'columnas' => count($vista['columnas']),
                'filas' => $this->filas((string) $vista['nombre']),
            ];
        }

        return [
            'vistas' => $vistas,
            'pendientes' => DiccionarioAnalitica::pendientes(),
            'superset' => config('superset.url') !== '' ? config('superset.url').'/dashboard/new/' : null,
        ];
    }

    private function filas(string $vista): ?int
    {
        try {
            return (int) DB::table(EsquemaAnalitica::tabla($vista))->count();
        } catch (Throwable) {
            return null;
        }
    }
}
