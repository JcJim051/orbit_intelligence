<?php

namespace App\Filament\Clusters\Geography\Pages;

use App\Enums\UserRole;
use App\Filament\Clusters\Geography\GeographyCluster;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ManageGeoViewer extends Page
{
    protected static ?string $cluster = GeographyCluster::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'geovisores/{geoViewer}/gestionar';

    protected string $view = 'filament.clusters.geography.pages.manage-geo-viewer';

    public GeoViewer $geoViewer;

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessSpatialGovernance() ?? false;
    }

    public function mount(GeoViewer $geoViewer): void
    {
        abort_unless(auth()->user()?->can('view', $geoViewer), 403);
        $this->geoViewer = $geoViewer->load([
            'approver',
            'collaborators',
            'layers' => fn ($query) => $query->orderBy('geo_viewer_layers.sort_order'),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->geoViewer->name;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return [
            'layers' => GeoLayer::query()->orderByDesc('active')->orderBy('group_name')->orderBy('name')->get(),
            'collaboratorCandidates' => User::query()
                ->where('active', true)
                ->whereIn('role', [UserRole::Admin->value, UserRole::SiidManager->value])
                ->orderBy('name')
                ->get(),
        ];
    }
}
