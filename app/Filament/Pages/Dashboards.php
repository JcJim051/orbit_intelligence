<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Dashboards extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static ?string $navigationLabel = 'Dashboards';

    protected static ?string $title = 'Dashboards interactivos';

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
            'description' => 'Construya tableros con indicadores, gráficas, mapas y fuentes tabulares versionadas.',
            'status' => null,
            'actions' => [
                ['label' => 'Administrar dashboards', 'url' => Workspace::getUrl(['workspace' => 'tableros']), 'primary' => true],
                ['label' => 'Fuentes tabulares', 'url' => Workspace::getUrl(['workspace' => 'fuentes-tabulares'])],
            ],
            'steps' => [
                ['title' => 'Conectar', 'description' => 'Fuentes autorizadas'],
                ['title' => 'Diseñar', 'description' => 'Componentes y diseño'],
                ['title' => 'Previsualizar', 'description' => 'Escritorio y móvil'],
                ['title' => 'Publicar', 'description' => 'Versión protegida'],
            ],
        ];
    }
}
