<?php

namespace App\Filament\Pages;

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DriveConnectionController;
use App\Http\Controllers\Admin\GeoViewerController;
use App\Http\Controllers\Admin\IndicatorController;
use App\Http\Controllers\Admin\InvestmentEntityController as AdminInvestmentEntityController;
use App\Http\Controllers\Admin\OpenDataSourceController;
use App\Http\Controllers\Admin\PostgisConnectionController;
use App\Http\Controllers\Admin\SpatialDatasetController;
use App\Http\Controllers\Admin\SpatialImportController;
use App\Http\Controllers\Admin\TabularDataSourceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\Investment\InvestmentDashboardController;
use App\Http\Controllers\Investment\InvestmentEntityController;
use App\Http\Controllers\Investment\InvestmentProjectController;
use App\Http\Controllers\WebMeetingController;
use App\Models\Dashboard;
use App\Models\InvestmentEntity;
use App\Models\InvestmentProject;
use App\Models\Meeting;
use App\Models\User;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

class Workspace extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'espacios/{workspace}/{record?}';

    protected string $view = 'filament.pages.workspace';

    public string $workspace;

    public ?string $record = null;

    public function mount(string $workspace, ?string $record = null): void
    {
        abort_unless(array_key_exists($workspace, $this->workspaces()), 404);
        $this->workspace = $workspace;
        $this->record = $record;
        $this->authorizeWorkspace(auth()->user());
    }

    public function getTitle(): string
    {
        return $this->workspaces()[$this->workspace]['title'];
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $definition = $this->workspaces()[$this->workspace];
        /** @var Response|View $response */
        $response = app()->call(
            [app($definition['controller']), $definition['method']],
            $this->routeParameters(),
        );
        abort_unless($response instanceof View, 500, 'La pantalla administrativa no produjo una vista compatible.');

        view()->share('filamentEmbedded', true);
        try {
            $legacyContent = $response->render();
        } finally {
            view()->share('filamentEmbedded', false);
        }

        return array_merge($response->getData(), [
            'legacyContent' => new HtmlString($legacyContent),
        ]);
    }

    /** @return array<string, array{title: string, controller: class-string, method: string, ability: string}> */
    private function workspaces(): array
    {
        return [
            'actas' => ['title' => 'Consultar actas', 'controller' => WebMeetingController::class, 'method' => 'index', 'ability' => 'member'],
            'actas-crear' => ['title' => 'Registrar reunión', 'controller' => WebMeetingController::class, 'method' => 'create', 'ability' => 'member'],
            'acta' => ['title' => 'Detalle de reunión', 'controller' => WebMeetingController::class, 'method' => 'show', 'ability' => 'member'],
            'cargas-qgis' => ['title' => 'Cargas desde QGIS', 'controller' => SpatialImportController::class, 'method' => 'index', 'ability' => 'admin'],
            'catalogo-datos' => ['title' => 'Catálogo de datos', 'controller' => SpatialDatasetController::class, 'method' => 'index', 'ability' => 'spatial'],
            'geovisores' => ['title' => 'Capas y geovisores', 'controller' => GeoViewerController::class, 'method' => 'index', 'ability' => 'spatial'],
            'fuentes-abiertas' => ['title' => 'Crear visor desde Datos Abiertos', 'controller' => OpenDataSourceController::class, 'method' => 'index', 'ability' => 'spatial'],
            'infraestructura' => ['title' => 'Infraestructura PostGIS', 'controller' => PostgisConnectionController::class, 'method' => 'index', 'ability' => 'admin'],
            'tableros' => ['title' => 'Dashboards interactivos', 'controller' => DashboardController::class, 'method' => 'index', 'ability' => 'dashboards'],
            'tablero' => ['title' => 'Constructor de dashboard', 'controller' => DashboardController::class, 'method' => 'edit', 'ability' => 'dashboards'],
            'fuentes-tabulares' => ['title' => 'Fuentes tabulares', 'controller' => TabularDataSourceController::class, 'method' => 'index', 'ability' => 'dashboards'],
            'indicadores' => ['title' => 'Indicadores', 'controller' => IndicatorController::class, 'method' => 'index', 'ability' => 'dashboards'],
            'inversion' => ['title' => 'Panorama de inversión pública', 'controller' => InvestmentDashboardController::class, 'method' => '__invoke', 'ability' => 'member'],
            'proyectos' => ['title' => 'Proyectos de inversión', 'controller' => InvestmentProjectController::class, 'method' => 'index', 'ability' => 'member'],
            'proyecto' => ['title' => 'Detalle del proyecto', 'controller' => InvestmentProjectController::class, 'method' => 'show', 'ability' => 'member'],
            'entidad' => ['title' => 'Entidad descentralizada', 'controller' => InvestmentEntityController::class, 'method' => 'show', 'ability' => 'member'],
            'clasificaciones' => ['title' => 'Entidades y clasificación', 'controller' => AdminInvestmentEntityController::class, 'method' => 'index', 'ability' => 'admin'],
            'usuarios' => ['title' => 'Equipo y permisos', 'controller' => UserController::class, 'method' => 'index', 'ability' => 'platform'],
            'drive' => ['title' => 'Google Drive', 'controller' => DriveConnectionController::class, 'method' => 'index', 'ability' => 'admin'],
            'dispositivos' => ['title' => 'Credenciales de dispositivos', 'controller' => DeviceTokenController::class, 'method' => 'index', 'ability' => 'admin'],
        ];
    }

    /** @return array<string, object> */
    private function routeParameters(): array
    {
        return match ($this->workspace) {
            'acta' => ['meeting' => Meeting::query()->findOrFail($this->record)],
            'tablero' => ['dashboard' => Dashboard::query()->where('slug', $this->record)->firstOrFail()],
            'proyecto' => ['investmentProject' => InvestmentProject::query()->findOrFail($this->record)],
            'entidad' => ['investmentEntity' => InvestmentEntity::query()->where('slug', $this->record)->firstOrFail()],
            default => [],
        };
    }

    private function authorizeWorkspace(?User $user): void
    {
        abort_unless($user, 403);
        $ability = $this->workspaces()[$this->workspace]['ability'];

        abort_unless(match ($ability) {
            'admin' => $user->isAdmin(),
            'spatial' => $user->canAccessSpatialGovernance(),
            'dashboards' => $user->canManageDashboards(),
            'platform' => $user->canAccessPlatformAdministration(),
            default => true,
        }, 403);
    }
}
