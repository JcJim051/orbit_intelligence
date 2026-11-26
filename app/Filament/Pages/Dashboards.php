<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Dashboards extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Analítica y tableros';

    protected static ?string $title = 'Analítica y tableros';

    protected static ?string $slug = 'dashboards';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.module';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageDashboards() ?? false;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return [
            'eyebrow' => 'Análisis y visualización',
            'description' => 'Prepare los datos y construya tableros desde SIID usando Superset como motor de analítica y visualización avanzada.',
            'status' => null,
            'actions' => [
                ['label' => 'Abrir constructor y catálogo', 'url' => Workspace::getUrl(['workspace' => 'tableros']), 'primary' => true],
            ],
            'steps' => [
                ['title' => 'Preparar', 'description' => 'Fuente y dataset validados'],
                ['title' => 'Construir', 'description' => 'Métricas, dimensiones y gráficos'],
                ['title' => 'Previsualizar', 'description' => 'Tablero embebido desde Superset'],
                ['title' => 'Publicar', 'description' => 'Catálogo, permisos y micrositio'],
            ],
        ];
    }
}
