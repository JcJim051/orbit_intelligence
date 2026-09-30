<?php

namespace App\Filament\Pages;

use App\Filament\Resources\UserResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class PlatformAdministration extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Administración';

    protected static ?string $title = 'Administración de plataforma';

    protected static ?string $slug = 'administracion';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.module';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessPlatformAdministration() ?? false;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $actions = [
            ['label' => 'Equipo y permisos', 'url' => UserResource::getUrl(), 'primary' => true],
        ];

        if (auth()->user()?->isAdmin()) {
            $actions[] = ['label' => 'Google Drive', 'url' => Workspace::getUrl(['workspace' => 'drive'])];
            $actions[] = ['label' => 'Dispositivos', 'url' => Workspace::getUrl(['workspace' => 'dispositivos'])];
        }

        return [
            'eyebrow' => 'Configuración restringida',
            'description' => 'Administre usuarios, permisos, integraciones y accesos técnicos de SIID 2.0.',
            'status' => null,
            'actions' => $actions,
            'steps' => [],
        ];
    }
}
