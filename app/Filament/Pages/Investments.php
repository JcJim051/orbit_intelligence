<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Investments extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Inversión pública';

    protected static ?string $title = 'Inversión pública';

    protected static ?string $slug = 'inversion-publica';

    protected static ?int $navigationSort = 40;

    protected string $view = 'filament.pages.module';

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $actions = [
            ['label' => 'Abrir panorama', 'url' => Workspace::getUrl(['workspace' => 'inversion']), 'primary' => true],
            ['label' => 'Consultar proyectos', 'url' => Workspace::getUrl(['workspace' => 'proyectos'])],
        ];

        if (auth()->user()?->isAdmin()) {
            $actions[] = ['label' => 'Clasificaciones', 'url' => Workspace::getUrl(['workspace' => 'clasificaciones'])];
        }

        return [
            'eyebrow' => 'Seguimiento territorial',
            'description' => 'Consulte proyectos, entidades responsables, recursos, avances, alertas y cobertura municipal.',
            'status' => null,
            'actions' => $actions,
            'steps' => [
                ['title' => 'Actualizar', 'description' => 'Fuentes oficiales'],
                ['title' => 'Clasificar', 'description' => 'Entidad y territorio'],
                ['title' => 'Analizar', 'description' => 'Avances y alertas'],
                ['title' => 'Gestionar', 'description' => 'Actas y decisiones'],
            ],
        ];
    }
}
