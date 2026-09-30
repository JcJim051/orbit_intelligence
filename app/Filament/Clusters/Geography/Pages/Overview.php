<?php

namespace App\Filament\Clusters\Geography\Pages;

use App\Enums\DatasetStatus;
use App\Enums\GeoViewerStatus;
use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Geography\GeographyCluster;
use App\Models\GeoViewer;
use App\Models\OpenDataSource;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class Overview extends Page
{
    protected static ?string $cluster = GeographyCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Bandeja SIG';

    protected static ?string $title = 'Inteligencia geográfica';

    protected static ?string $slug = 'inicio';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.clusters.geography.pages.overview';

    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessSpatialGovernance() ?? false;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $hasImports = Schema::hasTable('spatial_imports');
        $hasDatasets = Schema::hasTable('spatial_datasets');
        $hasViewers = Schema::hasTable('geo_viewers');
        $hasOpenData = Schema::hasTable('open_data_sources');

        $failedImports = $user->isAdmin() && $hasImports
            ? SpatialImport::query()->where('status', SpatialImportStatus::Failed->value)->latest()->limit(5)->get()
            : collect();
        $importsInPreparation = $user->isAdmin() && $hasImports
            ? SpatialImport::query()->whereIn('status', [SpatialImportStatus::Pending->value, SpatialImportStatus::StagingReady->value, SpatialImportStatus::Profiled->value, SpatialImportStatus::ContractDraft->value])->count()
            : 0;
        $openDataWithErrors = $hasOpenData
            ? $this->visibleOpenData($user)->whereNotNull('last_error')->count()
            : 0;
        $openDataInReview = $hasOpenData
            ? $this->visibleOpenData($user)->where('status', 'review')->count()
            : 0;
        $reviewViewers = $hasViewers
            ? $this->visibleViewers($user)->where('status', GeoViewerStatus::Review->value)->latest()->limit(5)->get()
            : collect();
        $publishedViewers = $hasViewers
            ? $this->visibleViewers($user)->where('status', GeoViewerStatus::Published->value)->count()
            : 0;
        $draftDatasets = $hasDatasets
            ? SpatialDataset::query()->where('status', DatasetStatus::Draft->value)->latest()->limit(5)->get()
            : collect();

        return [
            'stats' => [
                ['label' => 'Requieren acción', 'value' => $failedImports->count() + $openDataWithErrors, 'color' => 'danger'],
                ['label' => 'En preparación', 'value' => $importsInPreparation + $draftDatasets->count(), 'color' => 'warning'],
                ['label' => 'Pendientes de aprobación', 'value' => $reviewViewers->count() + $openDataInReview, 'color' => 'info'],
                ['label' => 'Publicados', 'value' => $publishedViewers, 'color' => 'success'],
            ],
            'failedImports' => $failedImports,
            'reviewViewers' => $reviewViewers,
            'draftDatasets' => $draftDatasets,
        ];
    }

    private function visibleViewers(User $user): Builder
    {
        return GeoViewer::query()->when(
            $user->role === UserRole::SiidManager,
            fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('collaborators', fn (Builder $query): Builder => $query->whereKey($user->id));
            }),
        );
    }

    private function visibleOpenData(User $user): Builder
    {
        return OpenDataSource::query()->when(
            $user->role === UserRole::SiidManager,
            fn (Builder $query): Builder => $query->where('owner_id', $user->id),
        );
    }
}
