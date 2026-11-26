<?php

namespace App\Filament\Pages;

use App\Models\MicrositioCifra;
use App\Models\MicrositioIndicador;
use App\Models\MicrositioMenu;
use App\Models\MicrositioPublicacion;
use App\Models\MicrositioTema;
use App\Services\Micrositio\PublicadorMicrositio;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MicrositioResumen extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = 'Micrositio GIEE';

    protected static ?string $navigationLabel = 'Resumen';

    protected static ?string $title = 'Micrositio GIEE';

    protected static ?string $slug = 'micrositio';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.micrositio-resumen';

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
                'filas' => $this->filas((string) ($arguments['seccion'] ?? '')),
            ]));
    }

    public function abrirDetalle(string $seccion): void
    {
        $secciones = $this->secciones();

        if (($secciones[$seccion]['total'] ?? 0) < 1) {
            return;
        }

        $this->mountAction('detalle', [
            'seccion' => $seccion,
            'titulo' => $secciones[$seccion]['titulo'],
            'columnas' => $secciones[$seccion]['columnas'],
        ]);
    }

    /**
     * @return array<string, array{titulo: string, total: int, columnas: list<string>}>
     */
    public function secciones(): array
    {
        return [
            'cifras' => ['titulo' => 'Cifras clave', 'total' => MicrositioCifra::query()->count(), 'columnas' => ['Valor', 'Fuente', 'Corte']],
            'temas' => ['titulo' => 'Temas', 'total' => MicrositioTema::query()->count(), 'columnas' => ['Tema']],
            'indicadores' => ['titulo' => 'Indicadores', 'total' => MicrositioIndicador::query()->count(), 'columnas' => ['Indicador', 'Público', 'Ejemplo']],
            'publicaciones' => ['titulo' => 'Publicaciones', 'total' => MicrositioPublicacion::query()->count(), 'columnas' => ['Tipo', 'Título']],
            'menu' => ['titulo' => 'Menú', 'total' => MicrositioMenu::query()->count(), 'columnas' => ['Texto', 'URL']],
        ];
    }

    /**
     * @return list<list<string>>
     */
    private function filas(string $seccion): array
    {
        return match ($seccion) {
            'cifras' => MicrositioCifra::query()->orderBy('orden')->get()->map(fn (MicrositioCifra $cifra): array => [
                $cifra->valor,
                $cifra->fuente,
                $cifra->fecha_corte?->format('d/m/Y') ?? '',
            ])->all(),
            'temas' => MicrositioTema::query()->orderBy('orden')->get()->map(fn (MicrositioTema $tema): array => [
                $tema->etiqueta(),
            ])->all(),
            'indicadores' => MicrositioIndicador::query()->with('tema')->orderBy('orden')->get()->map(fn (MicrositioIndicador $indicador): array => [
                $indicador->etiqueta(),
                $indicador->es_publico ? 'Sí' : 'No',
                $indicador->es_ejemplo ? 'Sí' : 'No',
            ])->all(),
            'publicaciones' => MicrositioPublicacion::query()->orderBy('orden')->get()->map(fn (MicrositioPublicacion $publicacion): array => [
                $publicacion->tipo,
                $publicacion->titulo,
            ])->all(),
            'menu' => MicrositioMenu::query()->orderBy('orden')->get()->map(fn (MicrositioMenu $enlace): array => [
                $enlace->texto,
                $enlace->url,
            ])->all(),
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $publicador = app(PublicadorMicrositio::class);

        return [
            'secciones' => collect($this->secciones())->map(fn (array $seccion, string $clave): array => $seccion + [
                'clave' => $clave,
                'publicada' => $publicador->seccionPublicada($clave),
            ])->all(),
        ];
    }
}
