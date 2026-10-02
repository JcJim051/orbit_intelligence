<?php

namespace App\Filament\Clusters\Geography;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class GeographyCluster extends Cluster
{
    protected static bool $shouldRegisterNavigation = false;

    protected static bool $shouldRegisterSubNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Inteligencia geográfica';

    protected static ?string $clusterBreadcrumb = 'Inteligencia geográfica';

    protected static ?string $slug = 'geografia';

    protected static ?int $navigationSort = 20;
}
