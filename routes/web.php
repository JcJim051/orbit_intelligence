<?php

use App\Filament\Pages\Workspace;
use App\Filament\Resources\UserResource;
use App\Http\Controllers\Admin\ActivePostgisConnectionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DashboardPreviewConfigController;
use App\Http\Controllers\Admin\DashboardPreviewController;
use App\Http\Controllers\Admin\DashboardPreviewQueryController;
use App\Http\Controllers\Admin\DashboardRelationshipDiagnosticController;
use App\Http\Controllers\Admin\DatasetFormDraftController;
use App\Http\Controllers\Admin\DatasetFormFieldController;
use App\Http\Controllers\Admin\DatasetFormPublicFieldsController;
use App\Http\Controllers\Admin\DriveConnectionController;
use App\Http\Controllers\Admin\GeoLayerController as AdminGeoLayerController;
use App\Http\Controllers\Admin\GeoLayerPublicAttributesController;
use App\Http\Controllers\Admin\GeoViewerController as AdminGeoViewerController;
use App\Http\Controllers\Admin\GeoViewerPreviewConfigController;
use App\Http\Controllers\Admin\GeoViewerPreviewController;
use App\Http\Controllers\Admin\IndicatorController as AdminIndicatorController;
use App\Http\Controllers\Admin\InvestmentEntityAssignmentController as AdminInvestmentEntityAssignmentController;
use App\Http\Controllers\Admin\InvestmentEntityController as AdminInvestmentEntityController;
use App\Http\Controllers\Admin\InvestmentSyncController;
use App\Http\Controllers\Admin\LocalPostgisBootstrapController;
use App\Http\Controllers\Admin\OpenDataPreviewController;
use App\Http\Controllers\Admin\OpenDataSourceController;
use App\Http\Controllers\Admin\PostgisConnectionController;
use App\Http\Controllers\Admin\PostgisPreparationController;
use App\Http\Controllers\Admin\PublishedDashboardController;
use App\Http\Controllers\Admin\PublishedDatasetFormController;
use App\Http\Controllers\Admin\PublishedGeoViewerController;
use App\Http\Controllers\Admin\PublishedIndicatorController;
use App\Http\Controllers\Admin\QgisEndpointController;
use App\Http\Controllers\Admin\QgisPostgisCredentialController;
use App\Http\Controllers\Admin\SpatialDatasetController;
use App\Http\Controllers\Admin\SpatialDatasetMaterializationController;
use App\Http\Controllers\Admin\SpatialImportAccessController;
use App\Http\Controllers\Admin\SpatialImportContractController;
use App\Http\Controllers\Admin\SpatialImportController;
use App\Http\Controllers\Admin\SpatialImportProfileController;
use App\Http\Controllers\Admin\TabularDataSourceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\DashboardEmbedController;
use App\Http\Controllers\DashboardQueryController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\GeoViewerDemoController;
use App\Http\Controllers\GeoViewerEmbedController;
use App\Http\Controllers\IndicatorDataSeriesController;
use App\Http\Controllers\IndicatorTechnicalSheetController;
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
use App\Http\Controllers\Intelligence\PlanDesarrolloConsultaController;
use App\Http\Controllers\Intelligence\PlanIndicativoController;
use App\Http\Controllers\Intelligence\ProyectoController;
use App\Http\Controllers\Intelligence\SectorMgaController;
use App\Http\Controllers\Intelligence\SeguimientoDependenciaController;
use App\Http\Controllers\Investment\InvestmentDashboardController;
use App\Http\Controllers\Investment\InvestmentEntityController;
use App\Http\Controllers\Investment\InvestmentMapController;
use App\Http\Controllers\Investment\MeetingInvestmentController;
use App\Http\Controllers\MeetingExportController;
use App\Http\Controllers\MeetingFileController;
use App\Http\Controllers\PublicDashboardConfigController;
use App\Http\Controllers\PublicEvaAgriculturalMapController;
use App\Http\Controllers\PublicGeoViewerConfigController;
use App\Http\Controllers\PublicIndicatorController;
use App\Http\Controllers\PublicMetaMunicipalBoundariesController;
use App\Http\Controllers\PublicOpenDataGeoJsonController;
use App\Http\Controllers\PublicSpatialDatasetGeoJsonController;
use App\Http\Controllers\WebMeetingController;
use App\Http\Middleware\AllowGeoViewerEmbedding;
use App\Models\Dashboard;
use App\Models\InvestmentEntity;
use App\Models\InvestmentProject;
use App\Models\Meeting;
use App\Models\MetaProducto;
use App\Models\Proyecto;
use Illuminate\Support\Facades\Route;

Route::get('/visores/{geoViewer:slug}/embed', GeoViewerEmbedController::class)
    ->middleware(AllowGeoViewerEmbedding::class)
    ->name('geo-viewers.embed');
Route::get('/demostracion/geovisor', GeoViewerDemoController::class)
    ->name('geo-viewers.demo');
Route::get('/api/public/visores/{geoViewer:slug}/config', PublicGeoViewerConfigController::class)
    ->name('geo-viewers.config');
Route::get('/api/public/geodata/limites-municipales-meta', PublicMetaMunicipalBoundariesController::class)
    ->name('geodata.meta-municipal-boundaries');
Route::get('/api/public/geodata/eva-agricola-meta', PublicEvaAgriculturalMapController::class)
    ->middleware('throttle:60,1')
    ->name('geodata.eva-agricultural-map');
Route::get('/api/public/geodata/fuentes-abiertas/{source:slug}', PublicOpenDataGeoJsonController::class)
    ->middleware('throttle:open-data')
    ->name('geodata.open-data-source');
Route::get('/api/public/geodata/{spatialDataset:slug}', PublicSpatialDatasetGeoJsonController::class)
    ->name('geodata.spatial-dataset');
Route::get('/tableros/{dashboard:slug}/embed', DashboardEmbedController::class)->middleware(AllowGeoViewerEmbedding::class)->name('dashboards.embed');
Route::get('/api/public/tableros/{dashboard:slug}/config', PublicDashboardConfigController::class)->name('dashboards.config');
Route::get('/api/public/tableros/{dashboard:slug}/consulta', DashboardQueryController::class)->middleware('throttle:dashboard-queries')->name('dashboards.query');
Route::get('/indicadores/{indicator:slug}', PublicIndicatorController::class)->name('indicators.show');
Route::get('/indicadores/{indicator:slug}/ficha-tecnica', IndicatorTechnicalSheetController::class)->name('indicators.technical-sheet');
Route::get('/indicadores/{indicator:slug}/serie-datos.csv', IndicatorDataSeriesController::class)->name('indicators.data-series');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => redirect('/gestion'));
    Route::get('/meetings', fn () => redirect(Workspace::getUrl(['workspace' => 'actas', ...request()->query()])))->name('meetings.index');
    Route::get('/meetings/create', fn () => redirect(Workspace::getUrl(['workspace' => 'actas-crear'])))->name('meetings.create');
    Route::post('/meetings', [WebMeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', fn (Meeting $meeting) => redirect(Workspace::getUrl([
        'workspace' => 'acta',
        'record' => $meeting->getRouteKey(),
    ])))->name('meetings.show');
    Route::get('/meetings/{meeting}/audio', [MeetingFileController::class, 'audio'])->name('meetings.audio');
    Route::get('/meetings/{meeting}/export/markdown', [MeetingExportController::class, 'markdown'])->name('meetings.export.markdown');
    Route::get('/meetings/{meeting}/export/pdf', [MeetingExportController::class, 'pdf'])->name('meetings.export.pdf');
    Route::get('/device-tokens', fn () => redirect(Workspace::getUrl(['workspace' => 'dispositivos'])))->name('tokens.index');
    Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/device-tokens/{token}', [DeviceTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/inversion-publica', fn () => redirect(Workspace::getUrl(['workspace' => 'inversion', ...request()->query()])))->name('investments.dashboard');
    Route::get('/inversion-publica/mapa-municipios', InvestmentMapController::class)->name('investments.map');
    Route::get('/inversion-publica/entidades/{investmentEntity}', fn (InvestmentEntity $investmentEntity) => redirect(Workspace::getUrl([
        'workspace' => 'entidad',
        'record' => $investmentEntity->getRouteKey(),
        ...request()->query(),
    ])))->name('investments.entities.show');
    Route::get('/inversion-publica/proyectos', fn () => redirect(Workspace::getUrl(['workspace' => 'proyectos', ...request()->query()])))->name('investments.projects.index');
    Route::get('/inversion-publica/proyectos/{investmentProject}', fn (InvestmentProject $investmentProject) => redirect(Workspace::getUrl([
        'workspace' => 'proyecto',
        'record' => $investmentProject->getRouteKey(),
        ...request()->query(),
    ])))->name('investments.projects.show');
    Route::post('/inversion-publica/proyectos/{investmentProject}/agenda', [MeetingInvestmentController::class, 'store'])->name('investments.meetings.store');
    Route::delete('/inversion-publica/proyectos/{investmentProject}/agenda/{meeting}', [MeetingInvestmentController::class, 'destroy'])->name('investments.meetings.destroy');
    Route::post('/inversion-publica/proyectos/{investmentProject}/compromisos', [MeetingInvestmentController::class, 'storeAction'])->name('investments.actions.store');
    Route::post('/inversion-publica/proyectos/{investmentProject}/decisiones', [MeetingInvestmentController::class, 'storeDecision'])->name('investments.decisions.store');

    Route::prefix('inteligencia')->name('intelligence.')->group(function () {
        require __DIR__.'/reporte_sectorial.php';

        Route::get('/dependencias', fn () => redirect(Workspace::getUrl(['workspace' => 'dependencias', ...request()->query()])))->name('dependencias.index');
        Route::get('/municipios', fn () => redirect(Workspace::getUrl(['workspace' => 'municipios', ...request()->query()])))->name('municipios.index');
        Route::get('/reglas-pasiva', fn () => redirect(Workspace::getUrl(['workspace' => 'reglas-pasiva', ...request()->query()])))->name('reglas-pasiva.index');
        Route::get('/seguimiento-dependencias/detalle', [SeguimientoDependenciaController::class, 'indexDetail'])->name('seguimiento-dependencias.detail');
        Route::get('/seguimiento-dependencias/{dependencia}/detalle', [SeguimientoDependenciaController::class, 'detail'])->whereNumber('dependencia')->name('seguimiento-dependencias.dependencia.detail');
        Route::get('/metas-resultado-por-pilar', MetaResultadoPorPilarController::class)->name('metas-resultado.por-pilar');
        Route::get('/metas-resultado-por-pilar/descargar', [MetaResultadoPorPilarController::class, 'download'])->name('metas-resultado.por-pilar.download');
        Route::get('/revision-ods', [OdsIndicatorReviewController::class, 'index'])->name('revision-ods.index');
        Route::post('/revision-ods/sugerencias', [OdsIndicatorReviewController::class, 'suggest'])->name('revision-ods.suggest');
        Route::post('/revision-ods/asignar-equipo', [OdsIndicatorReviewController::class, 'assignTeam'])->name('revision-ods.assign-team');
        Route::get('/revision-ods/{task}', [OdsIndicatorReviewController::class, 'show'])->name('revision-ods.show');
        Route::patch('/revision-ods/{task}', [OdsIndicatorReviewController::class, 'update'])->name('revision-ods.update');
        Route::post('/revision-ods/{task}/relaciones', [OdsIndicatorReviewController::class, 'storeLink'])->name('revision-ods.links.store');
        Route::patch('/revision-ods/{task}/relaciones/{link}', [OdsIndicatorReviewController::class, 'updateLink'])->name('revision-ods.links.update');
        Route::post('/revision-ods/{task}/comentarios', [OdsIndicatorReviewController::class, 'storeComment'])->name('revision-ods.comments.store');

        $planCatalogs = [
            'pilares' => [PddPilarController::class, 'pilar'],
            'ejes' => [PddEjeController::class, 'eje'],
            'lineas' => [PddLineaController::class, 'linea'],
            'programas' => [PddProgramaController::class, 'programa'],
            'subprogramas' => [PddSubprogramaController::class, 'subprograma'],
            'sectores-mga' => [SectorMgaController::class, 'sector'],
            'metas-producto' => [MetaProductoController::class, 'metaProducto'],
            'indicadores-resultado' => [IndicadorResultadoController::class, 'indicador'],
            'metas-resultado' => [MetaResultadoController::class, 'metaResultado'],
        ];

        foreach ($planCatalogs as $uri => [$controller]) {
            Route::get('/'.$uri, fn () => redirect(Workspace::getUrl(['workspace' => $uri, ...request()->query()])))->name($uri.'.index');
        }

        Route::get('/metas-producto/{metaProducto}', fn (MetaProducto $metaProducto) => redirect(Workspace::getUrl([
            'workspace' => 'meta-producto',
            'record' => $metaProducto->getRouteKey(),
        ])))
            ->whereNumber('metaProducto')
            ->name('metas-producto.show');

        Route::get('/proyectos-metas', fn () => redirect(Workspace::getUrl([
            'workspace' => 'metas-proyectos',
            ...request()->query(),
        ])))->name('proyectos-metas.index');

        Route::get('/plan-indicativo', fn () => redirect(Workspace::getUrl([
            'workspace' => 'plan-indicativo',
            ...request()->query(),
        ])))->name('plan-indicativo.index');

        Route::get('/proyectos-metas/{proyecto}', fn (Proyecto $proyecto) => redirect(Workspace::getUrl([
            'workspace' => 'metas-proyecto',
            'record' => $proyecto->getRouteKey(),
        ])))
            ->whereNumber('proyecto')
            ->name('proyectos-metas.show');

        // Detalle de solo lectura de los conteos del listado (misma visibilidad que el índice).
        $countDetailCatalogs = [
            ...array_diff_key($planCatalogs, ['metas-producto' => true]),
            'dependencias' => [DependenciaController::class, 'dependencia'],
        ];

        foreach ($countDetailCatalogs as $uri => [$controller, $parameter]) {
            Route::get('/'.$uri.'/{'.$parameter.'}/detalle', [$controller, 'detail'])->whereNumber($parameter)->name($uri.'.detail');
        }

        Route::get('/api/estructura', [PlanDesarrolloConsultaController::class, 'estructura'])->name('estructura');
        Route::get('/api/metas-resultado/{metaResultado}', [PlanDesarrolloConsultaController::class, 'metaResultado'])->name('metas-resultado.consulta');

        Route::middleware('role:admin')->group(function () use ($planCatalogs) {
            Route::get('/dependencias/crear', [DependenciaController::class, 'create'])->name('dependencias.create');
            Route::post('/dependencias', [DependenciaController::class, 'store'])->name('dependencias.store');
            Route::get('/dependencias/exportar', [DependenciaController::class, 'export'])->name('dependencias.export');
            Route::get('/dependencias/plantilla', [DependenciaController::class, 'template'])->name('dependencias.template');
            Route::post('/dependencias/importar', [DependenciaController::class, 'import'])->name('dependencias.import');
            Route::get('/dependencias/{dependencia}/editar', [DependenciaController::class, 'edit'])->name('dependencias.edit');
            Route::patch('/dependencias/{dependencia}', [DependenciaController::class, 'update'])->name('dependencias.update');
            Route::delete('/dependencias/{dependencia}', [DependenciaController::class, 'destroy'])->name('dependencias.destroy');

            Route::get('/municipios/crear', [MunicipioController::class, 'create'])->name('municipios.create');
            Route::post('/municipios', [MunicipioController::class, 'store'])->name('municipios.store');
            Route::get('/municipios/exportar', [MunicipioController::class, 'export'])->name('municipios.export');
            Route::get('/municipios/plantilla', [MunicipioController::class, 'template'])->name('municipios.template');
            Route::post('/municipios/importar', [MunicipioController::class, 'import'])->name('municipios.import');
            Route::get('/municipios/{municipio}/editar', [MunicipioController::class, 'edit'])->name('municipios.edit');
            Route::patch('/municipios/{municipio}', [MunicipioController::class, 'update'])->name('municipios.update');
            Route::delete('/municipios/{municipio}', [MunicipioController::class, 'destroy'])->name('municipios.destroy');

            Route::get('/reglas-pasiva/crear', [DependenciaReglaPasivaController::class, 'create'])->name('reglas-pasiva.create');
            Route::post('/reglas-pasiva', [DependenciaReglaPasivaController::class, 'store'])->name('reglas-pasiva.store');
            Route::get('/reglas-pasiva/exportar', [DependenciaReglaPasivaController::class, 'export'])->name('reglas-pasiva.export');
            Route::get('/reglas-pasiva/plantilla', [DependenciaReglaPasivaController::class, 'template'])->name('reglas-pasiva.template');
            Route::post('/reglas-pasiva/importar', [DependenciaReglaPasivaController::class, 'import'])->name('reglas-pasiva.import');
            Route::get('/reglas-pasiva/{regla}/editar', [DependenciaReglaPasivaController::class, 'edit'])->name('reglas-pasiva.edit');
            Route::patch('/reglas-pasiva/{regla}', [DependenciaReglaPasivaController::class, 'update'])->name('reglas-pasiva.update');
            Route::delete('/reglas-pasiva/{regla}', [DependenciaReglaPasivaController::class, 'destroy'])->name('reglas-pasiva.destroy');

            Route::get('/metas-producto/relaciones-proyectos/plantilla', [MetaProductoController::class, 'projectTemplate'])->name('metas-producto.projects.template');
            Route::post('/metas-producto/relaciones-proyectos', [MetaProductoController::class, 'importProjects'])->name('metas-producto.projects.import');
            Route::get('/proyectos-metas/relaciones', fn () => redirect(Workspace::getUrl([
                'workspace' => 'metas-proyectos-importar',
            ])))->name('proyectos-metas.relations.index');
            Route::get('/proyectos-metas/relaciones/plantilla', [ProyectoController::class, 'relationsTemplate'])->name('proyectos-metas.relations.template');
            Route::post('/proyectos-metas/relaciones', [ProyectoController::class, 'importRelations'])->name('proyectos-metas.relations.import');
            Route::patch('/plan-indicativo', [PlanIndicativoController::class, 'update'])->name('plan-indicativo.update');
            Route::get('/plan-indicativo/plantilla', [PlanIndicativoController::class, 'template'])->name('plan-indicativo.template');
            Route::post('/plan-indicativo/importar', [PlanIndicativoController::class, 'import'])->name('plan-indicativo.import');

            foreach ($planCatalogs as $uri => [$controller, $parameter]) {
                Route::get('/'.$uri.'/crear', [$controller, 'create'])->name($uri.'.create');
                Route::post('/'.$uri, [$controller, 'store'])->name($uri.'.store');
                Route::get('/'.$uri.'/exportar', [$controller, 'export'])->name($uri.'.export');
                Route::get('/'.$uri.'/plantilla', [$controller, 'template'])->name($uri.'.template');
                Route::post('/'.$uri.'/importar', [$controller, 'import'])->name($uri.'.import');
                Route::get('/'.$uri.'/{'.$parameter.'}/editar', $uri === 'metas-producto'
                    ? fn (MetaProducto $metaProducto) => redirect(Workspace::getUrl([
                        'workspace' => 'meta-producto-editar',
                        'record' => $metaProducto->getRouteKey(),
                    ]))
                    : [$controller, 'edit'])->name($uri.'.edit');
                Route::patch('/'.$uri.'/{'.$parameter.'}', [$controller, 'update'])->name($uri.'.update');
                Route::delete('/'.$uri.'/{'.$parameter.'}', [$controller, 'destroy'])->name($uri.'.destroy');
            }
        });
    });

    Route::middleware('role:admin,manager,siid_manager')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/tableros', fn () => redirect(Workspace::getUrl(['workspace' => 'tableros', ...request()->query()])))->name('dashboards.index');
        Route::post('/tableros', [AdminDashboardController::class, 'store'])->name('dashboards.store');
        Route::get('/tableros/{dashboard:slug}/editar', fn (Dashboard $dashboard) => redirect(Workspace::getUrl([
            'workspace' => 'tablero',
            'record' => $dashboard->slug,
        ])))->name('dashboards.edit');
        Route::patch('/tableros/{dashboard:slug}', [AdminDashboardController::class, 'update'])->name('dashboards.update');
        Route::post('/tableros/{dashboard:slug}/revision', [AdminDashboardController::class, 'submit'])->name('dashboards.submit');
        Route::put('/tableros/{dashboard:slug}/colaboradores', [AdminDashboardController::class, 'collaborators'])->name('dashboards.collaborators.update');
        Route::get('/tableros/{dashboard:slug}/preview', DashboardPreviewController::class)->middleware(AllowGeoViewerEmbedding::class)->name('dashboards.preview');
        Route::get('/tableros/{dashboard:slug}/preview-config', DashboardPreviewConfigController::class)->name('dashboards.preview-config');
        Route::get('/tableros/{dashboard:slug}/preview-query', DashboardPreviewQueryController::class)->middleware('throttle:dashboard-queries')->name('dashboards.preview-query');
        Route::get('/tableros/{dashboard:slug}/diagnostico-relacion', DashboardRelationshipDiagnosticController::class)->name('dashboards.relationship-diagnostic');
        Route::get('/fuentes-tabulares', fn () => redirect(Workspace::getUrl(['workspace' => 'fuentes-tabulares'])))->name('data-sources.index');
        Route::post('/fuentes-tabulares', [TabularDataSourceController::class, 'store'])->middleware('throttle:uploads')->name('data-sources.store');
        Route::post('/fuentes-tabulares/{dataSource:slug}/versiones', [TabularDataSourceController::class, 'replace'])->middleware('throttle:uploads')->name('data-sources.versions.store');
        Route::put('/fuentes-tabulares/{dataSource:slug}/campos', [TabularDataSourceController::class, 'fields'])->name('data-sources.fields.update');
        Route::get('/indicadores', fn () => redirect(Workspace::getUrl(['workspace' => 'indicadores', ...request()->query()])))->name('indicators.index');
        Route::post('/indicadores', [AdminIndicatorController::class, 'store'])->middleware('throttle:uploads')->name('indicators.store');
        Route::patch('/indicadores/{indicator:slug}', [AdminIndicatorController::class, 'update'])->middleware('throttle:uploads')->name('indicators.update');
        Route::post('/indicadores/{indicator:slug}/revision', [AdminIndicatorController::class, 'submit'])->name('indicators.submit');
    });

    Route::middleware('role:admin,manager')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/tableros/{dashboard:slug}/publicacion', PublishedDashboardController::class)->name('dashboards.publication.store');
        Route::post('/indicadores/{indicator:slug}/publicacion', PublishedIndicatorController::class)->name('indicators.publication.store');
        Route::get('/users', fn () => redirect(UserResource::getUrl()))->name('users.index');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('role:admin,manager,management_support,siid_manager')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/geovisores', fn () => redirect(Workspace::getUrl(['workspace' => 'geovisores'])))->name('geo-viewers.index');
        Route::get('/geovisores/{geoViewer:slug}/preview', GeoViewerPreviewController::class)
            ->middleware(AllowGeoViewerEmbedding::class)
            ->name('geo-viewers.preview');
        Route::get('/geovisores/{geoViewer:slug}/preview-config', GeoViewerPreviewConfigController::class)
            ->name('geo-viewers.preview-config');
        Route::post('/geovisores/{geoViewer:slug}/publicacion', PublishedGeoViewerController::class)
            ->name('geo-viewers.publication.store');
        Route::get('/catalogo-datos', fn () => redirect(Workspace::getUrl(['workspace' => 'catalogo-datos'])))->name('spatial-datasets.index');
        Route::post('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/publicacion', [PublishedDatasetFormController::class, 'store'])->name('spatial-datasets.versions.publication.store')->scopeBindings();
        Route::post('/catalogo-datos/{spatialDataset:slug}/preparacion', SpatialDatasetMaterializationController::class)->name('spatial-datasets.materialization.store');
        Route::post('/geocapas/{geoLayer:slug}/atributos-publicos', GeoLayerPublicAttributesController::class)->name('geo-layers.public-attributes.update');
        Route::get('/fuentes-abiertas', fn () => redirect(Workspace::getUrl(['workspace' => 'fuentes-abiertas'])))->name('open-data-sources.index');
        Route::get('/fuentes-abiertas/{source:slug}/preview', OpenDataPreviewController::class)->name('open-data-sources.preview');
    });

    Route::middleware('role:admin,siid_manager')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/fuentes-abiertas/analizar', [OpenDataSourceController::class, 'analyze'])->middleware('throttle:open-data')->name('open-data-sources.analyze');
        Route::post('/fuentes-abiertas', [OpenDataSourceController::class, 'store'])->middleware('throttle:open-data')->name('open-data-sources.store');
        Route::patch('/fuentes-abiertas/{source:slug}', [OpenDataSourceController::class, 'update'])->name('open-data-sources.update');
        Route::post('/fuentes-abiertas/{source:slug}/revision', [OpenDataSourceController::class, 'submit'])->name('open-data-sources.submit');
        Route::post('/geovisores', [AdminGeoViewerController::class, 'store'])->name('geo-viewers.store');
        Route::patch('/geovisores/{geoViewer:slug}', [AdminGeoViewerController::class, 'update'])->name('geo-viewers.update');
        Route::put('/geovisores/{geoViewer:slug}/colaboradores', [AdminGeoViewerController::class, 'collaborators'])->name('geo-viewers.collaborators.update');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/geocapas', [AdminGeoLayerController::class, 'store'])->name('geo-layers.store');
        Route::patch('/geocapas/{geoLayer:slug}', [AdminGeoLayerController::class, 'update'])->name('geo-layers.update');
        Route::post('/catalogo-datos', [SpatialDatasetController::class, 'store'])->name('spatial-datasets.store');
        Route::patch('/catalogo-datos/{spatialDataset:slug}', [SpatialDatasetController::class, 'update'])->name('spatial-datasets.update');
        Route::post('/catalogo-datos/{spatialDataset:slug}/borradores', [DatasetFormDraftController::class, 'store'])->name('spatial-datasets.drafts.store');
        Route::post('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos-publicos', DatasetFormPublicFieldsController::class)->name('spatial-datasets.versions.public-fields.store')->scopeBindings();
        Route::post('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos', [DatasetFormFieldController::class, 'store'])->name('spatial-datasets.versions.fields.store')->scopeBindings();
        Route::patch('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos/{field}', [DatasetFormFieldController::class, 'update'])->name('spatial-datasets.versions.fields.update')->scopeBindings();
        Route::delete('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos/{field}', [DatasetFormFieldController::class, 'destroy'])->name('spatial-datasets.versions.fields.destroy')->scopeBindings();
        Route::get('/importaciones-sig', fn () => redirect(Workspace::getUrl(['workspace' => 'cargas-qgis'])))->name('spatial-imports.index');
        Route::post('/importaciones-sig', [SpatialImportController::class, 'store'])->name('spatial-imports.store');
        Route::post('/importaciones-sig/{spatialImport}/acceso', SpatialImportAccessController::class)->name('spatial-imports.access.store');
        Route::post('/importaciones-sig/{spatialImport}/perfil', SpatialImportProfileController::class)->name('spatial-imports.profile.store');
        Route::post('/importaciones-sig/{spatialImport}/contrato', SpatialImportContractController::class)->name('spatial-imports.contract.store');
        Route::get('/infraestructura-sig', fn () => redirect(Workspace::getUrl(['workspace' => 'infraestructura'])))->name('postgis.index');
        Route::post('/infraestructura-sig/local', LocalPostgisBootstrapController::class)->name('postgis.local.store');
        Route::post('/infraestructura-sig/credencial-qgis', QgisPostgisCredentialController::class)->name('postgis.qgis-credential.store');
        Route::patch('/infraestructura-sig/direccion-qgis', QgisEndpointController::class)->name('postgis.qgis-endpoint.update');
        Route::post('/infraestructura-sig/conexion', [PostgisConnectionController::class, 'store'])->name('postgis.connection.store');
        Route::post('/infraestructura-sig/preparacion', PostgisPreparationController::class)->name('postgis.preparation.store');
        Route::post('/infraestructura-sig/activacion', [ActivePostgisConnectionController::class, 'store'])->name('postgis.activation.store');
        Route::delete('/infraestructura-sig/activacion', [ActivePostgisConnectionController::class, 'destroy'])->name('postgis.activation.destroy');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/password', [UserController::class, 'password'])->name('users.password.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::get('/drive', fn () => redirect(Workspace::getUrl(['workspace' => 'drive'])))->name('drive.index');
        Route::post('/drive', [DriveConnectionController::class, 'store'])->name('drive.store');
        Route::get('/drive/{connection}/connect', [DriveConnectionController::class, 'connect'])->name('drive.connect');
        Route::post('/drive/{connection}/verify', [DriveConnectionController::class, 'verify'])->name('drive.verify');
        Route::patch('/drive/{connection}/toggle', [DriveConnectionController::class, 'toggle'])->name('drive.toggle');
        Route::post('/drive-exports/{export}/retry', [DriveConnectionController::class, 'retry'])->name('drive-exports.retry');
        Route::post('/investment-sync', [InvestmentSyncController::class, 'store'])->name('investments.sync');
        Route::get('/investment-entities', fn () => redirect(Workspace::getUrl(['workspace' => 'clasificaciones', ...request()->query()])))->name('investment-entities.index');
        Route::patch('/investment-entities/{investmentEntity}', [AdminInvestmentEntityController::class, 'update'])->name('investment-entities.update');
        Route::post('/investment-projects/{investmentProject}/entity-assignment', [AdminInvestmentEntityAssignmentController::class, 'store'])->name('investment-entity-assignments.store');
        Route::patch('/investment-entity-assignments/{assignment}', [AdminInvestmentEntityAssignmentController::class, 'update'])->name('investment-entity-assignments.update');
    });
    Route::get('/oauth/google-drive/callback', [DriveConnectionController::class, 'callback'])->name('drive.callback');
});
