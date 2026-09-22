<?php

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
use App\Http\Controllers\Admin\InvestmentEntityAssignmentController as AdminInvestmentEntityAssignmentController;
use App\Http\Controllers\Admin\InvestmentEntityController as AdminInvestmentEntityController;
use App\Http\Controllers\Admin\InvestmentSyncController;
use App\Http\Controllers\Admin\LocalPostgisBootstrapController;
use App\Http\Controllers\Admin\PostgisConnectionController;
use App\Http\Controllers\Admin\PostgisPreparationController;
use App\Http\Controllers\Admin\PublishedDashboardController;
use App\Http\Controllers\Admin\PublishedDatasetFormController;
use App\Http\Controllers\Admin\PublishedGeoViewerController;
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
use App\Http\Controllers\Investment\InvestmentDashboardController;
use App\Http\Controllers\Investment\InvestmentEntityController;
use App\Http\Controllers\Investment\InvestmentMapController;
use App\Http\Controllers\Investment\InvestmentProjectController;
use App\Http\Controllers\Investment\MeetingInvestmentController;
use App\Http\Controllers\MeetingExportController;
use App\Http\Controllers\MeetingFileController;
use App\Http\Controllers\PublicDashboardConfigController;
use App\Http\Controllers\PublicGeoViewerConfigController;
use App\Http\Controllers\PublicMetaMunicipalBoundariesController;
use App\Http\Controllers\PublicSpatialDatasetGeoJsonController;
use App\Http\Controllers\WebMeetingController;
use App\Http\Middleware\AllowGeoViewerEmbedding;
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
Route::get('/api/public/geodata/{spatialDataset:slug}', PublicSpatialDatasetGeoJsonController::class)
    ->name('geodata.spatial-dataset');
Route::get('/tableros/{dashboard:slug}/embed', DashboardEmbedController::class)->middleware(AllowGeoViewerEmbedding::class)->name('dashboards.embed');
Route::get('/api/public/tableros/{dashboard:slug}/config', PublicDashboardConfigController::class)->name('dashboards.config');
Route::get('/api/public/tableros/{dashboard:slug}/consulta', DashboardQueryController::class)->middleware('throttle:dashboard-queries')->name('dashboards.query');

Route::middleware('guest')->group(function () {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
    Route::get('/', fn () => redirect()->route('meetings.index'));
    Route::get('/meetings', [WebMeetingController::class, 'index'])->name('meetings.index');
    Route::get('/meetings/create', [WebMeetingController::class, 'create'])->name('meetings.create');
    Route::post('/meetings', [WebMeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', [WebMeetingController::class, 'show'])->name('meetings.show');
    Route::get('/meetings/{meeting}/audio', [MeetingFileController::class, 'audio'])->name('meetings.audio');
    Route::get('/meetings/{meeting}/export/markdown', [MeetingExportController::class, 'markdown'])->name('meetings.export.markdown');
    Route::get('/meetings/{meeting}/export/pdf', [MeetingExportController::class, 'pdf'])->name('meetings.export.pdf');
    Route::get('/device-tokens', [DeviceTokenController::class, 'index'])->name('tokens.index');
    Route::post('/device-tokens', [DeviceTokenController::class, 'store'])->name('tokens.store');
    Route::delete('/device-tokens/{token}', [DeviceTokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('/inversion-publica', InvestmentDashboardController::class)->name('investments.dashboard');
    Route::get('/inversion-publica/mapa-municipios', InvestmentMapController::class)->name('investments.map');
    Route::get('/inversion-publica/entidades/{investmentEntity}', [InvestmentEntityController::class, 'show'])->name('investments.entities.show');
    Route::get('/inversion-publica/proyectos', [InvestmentProjectController::class, 'index'])->name('investments.projects.index');
    Route::get('/inversion-publica/proyectos/{investmentProject}', [InvestmentProjectController::class, 'show'])->name('investments.projects.show');
    Route::post('/inversion-publica/proyectos/{investmentProject}/agenda', [MeetingInvestmentController::class, 'store'])->name('investments.meetings.store');
    Route::delete('/inversion-publica/proyectos/{investmentProject}/agenda/{meeting}', [MeetingInvestmentController::class, 'destroy'])->name('investments.meetings.destroy');
    Route::post('/inversion-publica/proyectos/{investmentProject}/compromisos', [MeetingInvestmentController::class, 'storeAction'])->name('investments.actions.store');
    Route::post('/inversion-publica/proyectos/{investmentProject}/decisiones', [MeetingInvestmentController::class, 'storeDecision'])->name('investments.decisions.store');

    Route::middleware('role:admin,manager,siid_manager')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/tableros', [AdminDashboardController::class, 'index'])->name('dashboards.index');
        Route::post('/tableros', [AdminDashboardController::class, 'store'])->name('dashboards.store');
        Route::get('/tableros/{dashboard:slug}/editar', [AdminDashboardController::class, 'edit'])->name('dashboards.edit');
        Route::patch('/tableros/{dashboard:slug}', [AdminDashboardController::class, 'update'])->name('dashboards.update');
        Route::post('/tableros/{dashboard:slug}/revision', [AdminDashboardController::class, 'submit'])->name('dashboards.submit');
        Route::put('/tableros/{dashboard:slug}/colaboradores', [AdminDashboardController::class, 'collaborators'])->name('dashboards.collaborators.update');
        Route::get('/tableros/{dashboard:slug}/preview', DashboardPreviewController::class)->middleware(AllowGeoViewerEmbedding::class)->name('dashboards.preview');
        Route::get('/tableros/{dashboard:slug}/preview-config', DashboardPreviewConfigController::class)->name('dashboards.preview-config');
        Route::get('/tableros/{dashboard:slug}/preview-query', DashboardPreviewQueryController::class)->middleware('throttle:dashboard-queries')->name('dashboards.preview-query');
        Route::get('/tableros/{dashboard:slug}/diagnostico-relacion', DashboardRelationshipDiagnosticController::class)->name('dashboards.relationship-diagnostic');
        Route::get('/fuentes-tabulares', [TabularDataSourceController::class, 'index'])->name('data-sources.index');
        Route::post('/fuentes-tabulares', [TabularDataSourceController::class, 'store'])->middleware('throttle:uploads')->name('data-sources.store');
        Route::post('/fuentes-tabulares/{dataSource:slug}/versiones', [TabularDataSourceController::class, 'replace'])->middleware('throttle:uploads')->name('data-sources.versions.store');
        Route::put('/fuentes-tabulares/{dataSource:slug}/campos', [TabularDataSourceController::class, 'fields'])->name('data-sources.fields.update');
    });

    Route::middleware('role:admin,manager')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/tableros/{dashboard:slug}/publicacion', PublishedDashboardController::class)->name('dashboards.publication.store');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    });

    Route::middleware('role:admin,manager,management_support')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/geovisores', [AdminGeoViewerController::class, 'index'])->name('geo-viewers.index');
        Route::get('/geovisores/{geoViewer:slug}/preview', GeoViewerPreviewController::class)
            ->middleware(AllowGeoViewerEmbedding::class)
            ->name('geo-viewers.preview');
        Route::get('/geovisores/{geoViewer:slug}/preview-config', GeoViewerPreviewConfigController::class)
            ->name('geo-viewers.preview-config');
        Route::post('/geovisores/{geoViewer:slug}/publicacion', PublishedGeoViewerController::class)
            ->name('geo-viewers.publication.store');
        Route::get('/catalogo-datos', [SpatialDatasetController::class, 'index'])->name('spatial-datasets.index');
        Route::post('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/publicacion', [PublishedDatasetFormController::class, 'store'])->name('spatial-datasets.versions.publication.store')->scopeBindings();
        Route::post('/catalogo-datos/{spatialDataset:slug}/preparacion', SpatialDatasetMaterializationController::class)->name('spatial-datasets.materialization.store');
        Route::post('/geocapas/{geoLayer:slug}/atributos-publicos', GeoLayerPublicAttributesController::class)->name('geo-layers.public-attributes.update');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/geovisores', [AdminGeoViewerController::class, 'store'])->name('geo-viewers.store');
        Route::patch('/geovisores/{geoViewer:slug}', [AdminGeoViewerController::class, 'update'])->name('geo-viewers.update');
        Route::post('/geocapas', [AdminGeoLayerController::class, 'store'])->name('geo-layers.store');
        Route::patch('/geocapas/{geoLayer:slug}', [AdminGeoLayerController::class, 'update'])->name('geo-layers.update');
        Route::post('/catalogo-datos', [SpatialDatasetController::class, 'store'])->name('spatial-datasets.store');
        Route::patch('/catalogo-datos/{spatialDataset:slug}', [SpatialDatasetController::class, 'update'])->name('spatial-datasets.update');
        Route::post('/catalogo-datos/{spatialDataset:slug}/borradores', [DatasetFormDraftController::class, 'store'])->name('spatial-datasets.drafts.store');
        Route::post('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos-publicos', DatasetFormPublicFieldsController::class)->name('spatial-datasets.versions.public-fields.store')->scopeBindings();
        Route::post('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos', [DatasetFormFieldController::class, 'store'])->name('spatial-datasets.versions.fields.store')->scopeBindings();
        Route::patch('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos/{field}', [DatasetFormFieldController::class, 'update'])->name('spatial-datasets.versions.fields.update')->scopeBindings();
        Route::delete('/catalogo-datos/{spatialDataset:slug}/versiones/{version}/campos/{field}', [DatasetFormFieldController::class, 'destroy'])->name('spatial-datasets.versions.fields.destroy')->scopeBindings();
        Route::get('/importaciones-sig', [SpatialImportController::class, 'index'])->name('spatial-imports.index');
        Route::post('/importaciones-sig', [SpatialImportController::class, 'store'])->name('spatial-imports.store');
        Route::post('/importaciones-sig/{spatialImport}/acceso', SpatialImportAccessController::class)->name('spatial-imports.access.store');
        Route::post('/importaciones-sig/{spatialImport}/perfil', SpatialImportProfileController::class)->name('spatial-imports.profile.store');
        Route::post('/importaciones-sig/{spatialImport}/contrato', SpatialImportContractController::class)->name('spatial-imports.contract.store');
        Route::get('/infraestructura-sig', [PostgisConnectionController::class, 'index'])->name('postgis.index');
        Route::post('/infraestructura-sig/local', LocalPostgisBootstrapController::class)->name('postgis.local.store');
        Route::post('/infraestructura-sig/credencial-qgis', QgisPostgisCredentialController::class)->name('postgis.qgis-credential.store');
        Route::patch('/infraestructura-sig/direccion-qgis', QgisEndpointController::class)->name('postgis.qgis-endpoint.update');
        Route::post('/infraestructura-sig/conexion', [PostgisConnectionController::class, 'store'])->name('postgis.connection.store');
        Route::post('/infraestructura-sig/preparacion', PostgisPreparationController::class)->name('postgis.preparation.store');
        Route::post('/infraestructura-sig/activacion', [ActivePostgisConnectionController::class, 'store'])->name('postgis.activation.store');
        Route::delete('/infraestructura-sig/activacion', [ActivePostgisConnectionController::class, 'destroy'])->name('postgis.activation.destroy');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/drive', [DriveConnectionController::class, 'index'])->name('drive.index');
        Route::post('/drive', [DriveConnectionController::class, 'store'])->name('drive.store');
        Route::get('/drive/{connection}/connect', [DriveConnectionController::class, 'connect'])->name('drive.connect');
        Route::post('/drive/{connection}/verify', [DriveConnectionController::class, 'verify'])->name('drive.verify');
        Route::patch('/drive/{connection}/toggle', [DriveConnectionController::class, 'toggle'])->name('drive.toggle');
        Route::post('/drive-exports/{export}/retry', [DriveConnectionController::class, 'retry'])->name('drive-exports.retry');
        Route::post('/investment-sync', [InvestmentSyncController::class, 'store'])->name('investments.sync');
        Route::get('/investment-entities', [AdminInvestmentEntityController::class, 'index'])->name('investment-entities.index');
        Route::patch('/investment-entities/{investmentEntity}', [AdminInvestmentEntityController::class, 'update'])->name('investment-entities.update');
        Route::post('/investment-projects/{investmentProject}/entity-assignment', [AdminInvestmentEntityAssignmentController::class, 'store'])->name('investment-entity-assignments.store');
        Route::patch('/investment-entity-assignments/{assignment}', [AdminInvestmentEntityAssignmentController::class, 'update'])->name('investment-entity-assignments.update');
    });
    Route::get('/oauth/google-drive/callback', [DriveConnectionController::class, 'callback'])->name('drive.callback');
});
