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
        return [
            'eyebrow' => 'Próximo módulo',
            'description' => 'Este espacio alojará indicadores, metas institucionales, responsables, avances y alertas.',
            'status' => 'Próximamente',
            'actions' => [],
            'steps' => [
                ['title' => 'Definir', 'description' => 'Metas e indicadores'],
                ['title' => 'Programar', 'description' => 'Periodos y responsables'],
                ['title' => 'Reportar', 'description' => 'Avances y evidencias'],
                ['title' => 'Evaluar', 'description' => 'Alertas y cumplimiento'],
            ],
        ];
    }
}
