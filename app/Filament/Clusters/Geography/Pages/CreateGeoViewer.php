<?php

namespace App\Filament\Clusters\Geography\Pages;

use App\Filament\Clusters\Geography\GeographyCluster;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CreateGeoViewer extends Page
{
    protected static ?string $cluster = GeographyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlus;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Crear geovisor';

    protected static ?string $slug = 'geovisores/crear';

    protected string $view = 'filament.clusters.geography.pages.create-geo-viewer';

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageOpenDataSources() ?? false;
    }
}
