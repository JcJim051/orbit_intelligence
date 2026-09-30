<?php

namespace App\Filament\Pages;

use App\Enums\DashboardStatus;
use App\Enums\GeoViewerStatus;
use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Filament\Clusters\Geography\Pages\Overview as GeographyOverview;
use App\Models\AuditLog;
use App\Models\Dashboard;
use App\Models\GeoViewer;
use App\Models\Meeting;
use App\Models\OpenDataSource;
use App\Models\SpatialImport;
use App\Models\User;
use Filament\Pages\Dashboard as FilamentDashboard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Home extends FilamentDashboard
{
    protected static ?string $navigationLabel = 'Inicio';

    protected string $view = 'filament.pages.home';

    /** @var array<string, bool> */
    private array $availableTables = [];

    public function getTitle(): string|Htmlable
    {
        return 'Inicio';
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $modules = [
            [
                'name' => 'Actas y compromisos',
                'description' => 'Registre reuniones, revise actas y dé seguimiento a decisiones y compromisos.',
                'code' => 'AC',
                'url' => Actas::getUrl(),
                'color' => '#2563eb',
                'soft' => '#eff6ff',
                'available' => $this->hasTable('meetings'),
            ],
            [
                'name' => 'Inversión pública',
                'description' => 'Consulte proyectos, entidades, avances, alertas y cobertura territorial.',
                'code' => 'IP',
                'url' => Investments::getUrl(),
                'color' => '#d97706',
                'soft' => '#fffbeb',
                'available' => $this->hasTable('investment_projects'),
            ],
        ];

        if ($user->canAccessSpatialGovernance()) {
            $modules[] = [
                'name' => 'Inteligencia geográfica',
                'description' => 'Gestione cargas QGIS, conjuntos de datos, capas, fuentes abiertas y geovisores.',
                'code' => 'IG',
                'url' => GeographyOverview::getUrl(),
                'color' => '#059669',
                'soft' => '#ecfdf5',
                'available' => $this->hasTable('geo_viewers') && $this->hasTable('spatial_datasets'),
            ];
        }

        if ($user->canManageDashboards()) {
            $dashboardsAvailable = $this->hasTable('dashboards') && $this->hasTable('tabular_data_sources');
            $modules[] = [
                'name' => 'Dashboards',
                'description' => $dashboardsAvailable
                    ? 'Combine mapas, indicadores y fuentes tabulares en tableros interactivos.'
                    : 'Este módulo requiere ejecutar sus migraciones antes de utilizarlo.',
                'code' => 'DB',
                'url' => $dashboardsAvailable ? Dashboards::getUrl() : null,
                'color' => '#7c3aed',
                'soft' => '#f5f3ff',
                'available' => $dashboardsAvailable,
            ];
        }

        if ($user->canAccessManagementGoals()) {
            $modules[] = [
                'name' => 'Seguimiento a metas',
                'description' => 'Espacio reservado para el próximo módulo de indicadores y metas institucionales.',
                'code' => 'SM',
                'url' => Goals::getUrl(),
                'color' => '#db2777',
                'soft' => '#fdf2f8',
                'comingSoon' => true,
                'available' => true,
            ];
        }

        if ($user->canAccessPlatformAdministration()) {
            $modules[] = [
                'name' => 'Administración',
                'description' => 'Administre equipo, permisos, integraciones y configuración técnica.',
                'code' => 'AD',
                'url' => PlatformAdministration::getUrl(),
                'color' => '#475569',
                'soft' => '#f8fafc',
                'available' => true,
            ];
        }

        return [
            'modules' => $modules,
            'stats' => [
                ['label' => 'Actas visibles', 'value' => $this->hasTable('meetings') ? $this->visibleMeetings($user)->count() : 0],
                ['label' => 'Pendientes de revisión', 'value' => $this->pendingReviews($user)],
                ['label' => 'Procesos con alerta', 'value' => $this->processAlerts($user)],
                ['label' => 'Publicaciones activas', 'value' => $this->publishedProducts($user)],
            ],
            'activity' => $this->recentActivity($user),
        ];
    }

    private function visibleMeetings(User $user): Builder
    {
        return Meeting::query()
            ->when(! $user->isAdmin(), function ($query) use ($user): void {
                $query->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                    if ($user->isReviewer()) {
                        $query->orWhere('reviewer_id', $user->id);
                    }
                });
            });
    }

    private function pendingReviews(User $user): int
    {
        if (! $user->canAccessSpatialGovernance() && ! $user->canManageDashboards()) {
            return 0;
        }

        $total = 0;
        if ($user->canAccessSpatialGovernance() && $this->hasTable('geo_viewers')) {
            $total += $this->visibleViewers($user)->where('status', GeoViewerStatus::Review->value)->count();
        }
        if ($user->canManageDashboards() && $this->hasTable('dashboards')) {
            $total += $this->visibleDashboards($user)->where('status', DashboardStatus::PendingReview->value)->count();
        }

        return $total;
    }

    private function processAlerts(User $user): int
    {
        if (! $user->canAccessSpatialGovernance()) {
            return 0;
        }

        $failedImports = $user->isAdmin() && $this->hasTable('spatial_imports')
            ? SpatialImport::query()->where('status', SpatialImportStatus::Failed->value)->count()
            : 0;

        $openDataErrors = $this->hasTable('open_data_sources')
            ? OpenDataSource::query()
                ->when($user->role === UserRole::SiidManager, fn (Builder $query): Builder => $query->where('owner_id', $user->id))
                ->whereNotNull('last_error')
                ->count()
            : 0;

        return $failedImports + $openDataErrors;
    }

    private function publishedProducts(User $user): int
    {
        $total = 0;
        if ($user->canAccessSpatialGovernance() && $this->hasTable('geo_viewers')) {
            $total += $this->visibleViewers($user)->where('status', GeoViewerStatus::Published->value)->count();
        }
        if ($user->canManageDashboards() && $this->hasTable('dashboards')) {
            $total += $this->visibleDashboards($user)->where('status', DashboardStatus::Published->value)->count();
        }

        return $total;
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

    private function visibleDashboards(User $user): Builder
    {
        return Dashboard::query()->when(
            $user->role === UserRole::SiidManager,
            fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('collaborators', fn (Builder $query): Builder => $query->whereKey($user->id));
            }),
        );
    }

    /** @return Collection<int, AuditLog> */
    private function recentActivity(User $user): Collection
    {
        if (! $this->hasTable('audit_logs')) {
            return collect();
        }

        return AuditLog::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('actor_id', $user->id))
            ->latest('created_at')
            ->limit(5)
            ->get();
    }

    private function hasTable(string $table): bool
    {
        return $this->availableTables[$table] ??= Schema::hasTable($table);
    }
}
