<?php

namespace App\Filament\Clusters\Geography\Pages;

use App\Filament\Clusters\Geography\GeographyCluster;
use App\Filament\Pages\Workspace;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Infrastructure extends Page
{
    protected static ?string $cluster = GeographyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static ?string $navigationLabel = 'Infraestructura';

    protected static ?string $title = 'Infraestructura geográfica';

    protected static ?string $slug = 'infraestructura';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.module';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return [
            'eyebrow' => 'Acceso técnico restringido',
            'description' => 'Verifique la conexión PostGIS, el endpoint de QGIS y las credenciales administradas de la plataforma.',
            'status' => null,
            'actions' => [
                ['label' => 'Administrar PostGIS', 'url' => Workspace::getUrl(['workspace' => 'infraestructura']), 'primary' => true],
                ['label' => 'Credenciales de dispositivos', 'url' => Workspace::getUrl(['workspace' => 'dispositivos'])],
            ],
            'steps' => [
                ['title' => 'Conectar', 'description' => 'Credenciales cifradas'],
                ['title' => 'Verificar', 'description' => 'Migraciones y estructuras'],
                ['title' => 'Publicar', 'description' => 'Servicios y vistas'],
                ['title' => 'Supervisar', 'description' => 'Estado y diagnósticos'],
            ],
        ];
    }
}
