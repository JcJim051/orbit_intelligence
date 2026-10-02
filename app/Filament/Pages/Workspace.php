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
use App\Http\Controllers\Intelligence\DependenciaController;
use App\Http\Controllers\Intelligence\DependenciaReglaPasivaController;
use App\Http\Controllers\Intelligence\IndicadorResultadoController;
use App\Http\Controllers\Intelligence\MetaProductoController;
use App\Http\Controllers\Intelligence\MetaResultadoController;
use App\Http\Controllers\Intelligence\MetaResultadoPorPilarController;
use App\Http\Controllers\Intelligence\MunicipioController;
use App\Http\Controllers\Intelligence\OdsIndicatorReviewController;
use App\Http\Controllers\Intelligence\PddEjeController;
use App\Http\Controllers\Intelligence\PddLineaController;
use App\Http\Controllers\Intelligence\PddPilarController;
use App\Http\Controllers\Intelligence\PddProgramaController;
use App\Http\Controllers\Intelligence\PddSubprogramaController;
use App\Http\Controllers\Intelligence\ReporteSectorial\PasivaController;
use App\Http\Controllers\Intelligence\ReporteSectorial\SeguimientoController;
use App\Http\Controllers\Intelligence\SectorMgaController;
use App\Http\Controllers\Investment\InvestmentDashboardController;
use App\Http\Controllers\Investment\InvestmentEntityController;
use App\Http\Controllers\Investment\InvestmentProjectController;
use App\Http\Controllers\WebMeetingController;
use App\Models\Dashboard;
use App\Models\InvestmentEntity;
use App\Models\InvestmentProject;
use App\Models\Meeting;
use App\Models\IndicadorResultadoOdsReview;
use App\Models\Seguimiento;
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
            'reporte-mensual' => ['title' => 'Reporte mensual sectorial', 'controller' => SeguimientoController::class, 'method' => 'index', 'ability' => 'goals'],
            'seguimiento' => ['title' => 'Detalle del seguimiento mensual', 'controller' => SeguimientoController::class, 'method' => 'show', 'ability' => 'member'],
            'seguimiento-editar' => ['title' => 'Editar seguimiento mensual', 'controller' => SeguimientoController::class, 'method' => 'edit', 'ability' => 'goals'],
            'seguimiento-datos-base' => ['title' => 'Datos base del seguimiento mensual', 'controller' => SeguimientoController::class, 'method' => 'datosBase', 'ability' => 'member'],
            'pasiva-lineas' => ['title' => 'Líneas de pasiva', 'controller' => PasivaController::class, 'method' => 'index', 'ability' => 'member'],
            'dependencias' => ['title' => 'Dependencias', 'controller' => DependenciaController::class, 'method' => 'index', 'ability' => 'goals'],
            'municipios' => ['title' => 'Municipios', 'controller' => MunicipioController::class, 'method' => 'index', 'ability' => 'goals'],
            'reglas-pasiva' => ['title' => 'Reglas de pasiva', 'controller' => DependenciaReglaPasivaController::class, 'method' => 'index', 'ability' => 'goals'],
            'pilares' => ['title' => 'Pilares PDD', 'controller' => PddPilarController::class, 'method' => 'index', 'ability' => 'goals'],
            'ejes' => ['title' => 'Ejes PDD', 'controller' => PddEjeController::class, 'method' => 'index', 'ability' => 'goals'],
            'lineas' => ['title' => 'Líneas PDD', 'controller' => PddLineaController::class, 'method' => 'index', 'ability' => 'goals'],
            'programas' => ['title' => 'Programas PDD', 'controller' => PddProgramaController::class, 'method' => 'index', 'ability' => 'goals'],
            'subprogramas' => ['title' => 'Subprogramas PDD', 'controller' => PddSubprogramaController::class, 'method' => 'index', 'ability' => 'goals'],
            'sectores-mga' => ['title' => 'Sectores MGA', 'controller' => SectorMgaController::class, 'method' => 'index', 'ability' => 'goals'],
            'metas-producto' => ['title' => 'Metas de producto', 'controller' => MetaProductoController::class, 'method' => 'index', 'ability' => 'goals'],
            'indicadores-resultado' => ['title' => 'Indicadores de resultado', 'controller' => IndicadorResultadoController::class, 'method' => 'index', 'ability' => 'goals'],
            'metas-resultado' => ['title' => 'Metas de resultado', 'controller' => MetaResultadoController::class, 'method' => 'index', 'ability' => 'goals'],
            'metas-resultado-por-pilar' => ['title' => 'Metas resultado por pilar', 'controller' => MetaResultadoPorPilarController::class, 'method' => '__invoke', 'ability' => 'goals'],
            'revision-ods' => ['title' => 'Revisión ODS', 'controller' => OdsIndicatorReviewController::class, 'method' => 'index', 'ability' => 'ods'],
            'revision-ods-detalle' => ['title' => 'Detalle revisión ODS', 'controller' => OdsIndicatorReviewController::class, 'method' => 'show', 'ability' => 'ods'],
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
            'seguimiento', 'seguimiento-editar', 'seguimiento-datos-base', 'pasiva-lineas' => ['seguimiento' => Seguimiento::query()->findOrFail($this->record)],
            'revision-ods-detalle' => ['task' => IndicadorResultadoOdsReview::query()->findOrFail($this->record)],
            default => [],
        };
    }

    private function authorizeWorkspace(?User $user): void
    {
        abort_unless($user, 403);

        if ($user->isDedicatedOdsReviewer()) {
            abort_unless(in_array($this->workspace, ['revision-ods', 'revision-ods-detalle'], true), 403);

            return;
        }

        $ability = $this->workspaces()[$this->workspace]['ability'];

        abort_unless(match ($ability) {
            'admin' => $user->isAdmin(),
            'spatial' => $user->canAccessSpatialGovernance(),
            'dashboards' => $user->canManageDashboards(),
            'platform' => $user->canAccessPlatformAdministration(),
            'goals' => $user->canAccessManagementGoals(),
            'ods' => $user->canReviewOdsIndicators(),
            default => true,
        }, 403);
    }
}
