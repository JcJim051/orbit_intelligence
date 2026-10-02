<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Goals extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Seguimiento a metas';

    protected static ?string $title = 'Seguimiento a metas';

    protected static ?string $slug = 'metas';

    protected static ?int $navigationSort = 50;

    protected string $view = 'filament.pages.module';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessManagementGoals() ?? false;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        if (auth()->user()?->isDedicatedOdsReviewer()) {
            return [
                'eyebrow' => 'Seguimiento a metas',
                'description' => 'Su acceso está limitado a la revisión de relaciones entre indicadores de resultado del PDD e indicadores ODS.',
                'status' => null,
                'actions' => [
                    ['label' => 'Abrir Revisión ODS', 'url' => Workspace::getUrl(['workspace' => 'revision-ods']), 'primary' => true],
                ],
                'steps' => [
                    ['title' => 'Revisar', 'description' => 'Indicadores asignados'],
                    ['title' => 'Comentar', 'description' => 'Trazabilidad humana'],
                    ['title' => 'Rechazar', 'description' => 'Descartar sugerencias no pertinentes'],
                    ['title' => 'Confirmar', 'description' => 'Validación final autorizada'],
                ],
            ];
        }

        return [
            'eyebrow' => 'Seguimiento institucional',
            'description' => 'Gestione el reporte mensual sectorial, revise pasivas, techos, avances, evidencias y mantenga los catálogos base del Plan de Desarrollo.',
            'status' => null,
            'actions' => [
                ['label' => 'Abrir reporte mensual', 'url' => Workspace::getUrl(['workspace' => 'reporte-mensual']), 'primary' => true],
                ['label' => 'Dependencias', 'url' => Workspace::getUrl(['workspace' => 'dependencias'])],
                ['label' => 'Catálogos PDD', 'url' => Workspace::getUrl(['workspace' => 'pilares'])],
            ],
            'steps' => [
                ['title' => 'Preparar', 'description' => 'Catálogos, dependencias, municipios y reglas de pasiva'],
                ['title' => 'Abrir corte', 'description' => 'Seguimiento mensual por vigencia y mes'],
                ['title' => 'Reportar', 'description' => 'Pasiva, techos, ejecución, avances y evidencias'],
                ['title' => 'Revisar', 'description' => 'Aprobación, devolución, alertas y cierre del corte'],
            ],
        ];
    }
}
