<?php

namespace App\Filament\Clusters\Geography\Pages;

use App\Filament\Clusters\Geography\GeographyCluster;
use App\Models\GeoViewer;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class GeoViewerPreview extends Page
{
    protected static ?string $cluster = GeographyCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'geovisores/{geoViewer}/previsualizar';

    protected string $view = 'filament.clusters.geography.pages.geo-viewer-preview';

    public GeoViewer $geoViewer;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessSpatialGovernance() ?? false;
    }

    public function mount(GeoViewer $geoViewer): void
    {
        abort_unless(auth()->user()?->can('view', $geoViewer), 403);
        $this->geoViewer = $geoViewer;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Previsualizar · '.$this->geoViewer->name;
    }
}
