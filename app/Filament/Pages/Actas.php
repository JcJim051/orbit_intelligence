<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Actas extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Actas';

    protected static ?string $title = 'Actas y compromisos';

    protected static ?string $slug = 'actas';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.module';

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return [
            'eyebrow' => 'Trabajo institucional',
            'description' => 'Capture reuniones, revise la transcripción, apruebe el acta y haga seguimiento a compromisos.',
            'status' => null,
            'actions' => [
                ['label' => 'Ver actas', 'url' => Workspace::getUrl(['workspace' => 'actas']), 'primary' => true],
                ['label' => 'Crear reunión', 'url' => Workspace::getUrl(['workspace' => 'actas-crear'])],
            ],
            'steps' => [
                ['title' => 'Registrar', 'description' => 'Datos y grabación'],
                ['title' => 'Procesar', 'description' => 'Transcripción y análisis'],
                ['title' => 'Revisar', 'description' => 'Decisiones y compromisos'],
                ['title' => 'Aprobar', 'description' => 'Acta y exportación'],
            ],
        ];
    }
}
