<?php

use App\Filament\Pages\Workspace;
use App\Http\Controllers\Intelligence\ReporteSectorial\AnaliticaSeguimientoController;
use App\Http\Controllers\Intelligence\ReporteSectorial\CargaHistoricaMetasController;
use App\Http\Controllers\Intelligence\ReporteSectorial\EvidenciaController;
use App\Http\Controllers\Intelligence\ReporteSectorial\PasivaController;
use App\Http\Controllers\Intelligence\ReporteSectorial\ReporteProyectoController;
use App\Http\Controllers\Intelligence\ReporteSectorial\SeguimientoController;
use App\Http\Controllers\Intelligence\ReporteSectorial\TechoController;
use App\Models\Proyecto;
use App\Models\Seguimiento;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reporte mensual sectorial (In-Orbit Intelligence)
|--------------------------------------------------------------------------
| Se incluye dentro del grupo auth + active, prefijo /inteligencia y nombre intelligence.
| {proyecto}, {actividad}, {techo} y {evidencia} se resuelven con el scope sectorial (404 entre sectores);
| las acciones se autorizan con las policies de cada modelo.
*/

Route::prefix('reporte-mensual')->name('reporte-mensual.')->group(function () {
    Route::get('/', fn () => redirect(Workspace::getUrl(['workspace' => 'reporte-mensual', ...request()->query()])))->name('index');
    Route::post('/', [SeguimientoController::class, 'store'])->name('store');
    Route::get('/evidencias/{evidencia}', [EvidenciaController::class, 'show'])->name('evidencias.show');
    Route::get('/evidencias/{evidencia}/ver', [EvidenciaController::class, 'preview'])->name('evidencias.preview');
    Route::delete('/evidencias/{evidencia}', [EvidenciaController::class, 'destroy'])->name('evidencias.destroy');
    Route::post('/techos/{techo}/ajuste', [TechoController::class, 'ajustar'])->name('techos.ajustar');
    Route::patch('/techos/{techo}', [TechoController::class, 'update'])->name('techos.update');
    Route::delete('/techos/{techo}', [TechoController::class, 'destroy'])->name('techos.destroy');

    Route::get('/{seguimiento}', fn (Seguimiento $seguimiento) => redirect(Workspace::getUrl([
        'workspace' => 'seguimiento',
        'record' => $seguimiento->getRouteKey(),
        ...request()->query(),
    ])))->name('show');
    Route::get('/{seguimiento}/editar', fn (Seguimiento $seguimiento) => redirect(Workspace::getUrl([
        'workspace' => 'seguimiento-editar',
        'record' => $seguimiento->getRouteKey(),
        ...request()->query(),
    ])))->name('edit');
    Route::patch('/{seguimiento}', [SeguimientoController::class, 'update'])->name('update');
    Route::get('/{seguimiento}/datos-base', fn (Seguimiento $seguimiento) => redirect(Workspace::getUrl([
        'workspace' => 'seguimiento-datos-base',
        'record' => $seguimiento->getRouteKey(),
        ...request()->query(),
    ])))->name('datos-base');
    Route::post('/{seguimiento}/cerrar', [SeguimientoController::class, 'cerrar'])->name('cerrar');
    Route::post('/{seguimiento}/pasivas', [PasivaController::class, 'store'])->middleware('throttle:uploads')->name('pasivas.store');
    Route::get('/{seguimiento}/analitica', fn (Seguimiento $seguimiento) => redirect(Workspace::getUrl([
        'workspace' => 'seguimiento-analitica',
        'record' => $seguimiento->getRouteKey(),
        ...request()->query(),
    ])))->name('analitica.index');
    Route::get('/{seguimiento}/analitica/descargar', [AnaliticaSeguimientoController::class, 'download'])->name('analitica.download');
    Route::get('/{seguimiento}/metas-historicas', fn (Seguimiento $seguimiento) => redirect(Workspace::getUrl([
        'workspace' => 'seguimiento-metas-historicas',
        'record' => $seguimiento->getRouteKey(),
        ...request()->query(),
    ])))->name('historicas.index');
    Route::get('/{seguimiento}/metas-historicas/plantilla', [CargaHistoricaMetasController::class, 'plantilla'])->name('historicas.plantilla');
    Route::post('/{seguimiento}/metas-historicas/diagnostico', [CargaHistoricaMetasController::class, 'diagnosticar'])->middleware('throttle:uploads')->name('historicas.diagnosticar');
    Route::post('/{seguimiento}/metas-historicas/{carga}/importar', [CargaHistoricaMetasController::class, 'importar'])->name('historicas.importar');
    Route::get('/{seguimiento}/metas-historicas/{carga}/errores.csv', [CargaHistoricaMetasController::class, 'errores'])->name('historicas.errores');
    Route::get('/{seguimiento}/pasiva-lineas', fn (Seguimiento $seguimiento) => redirect(Workspace::getUrl([
        'workspace' => 'pasiva-lineas',
        'record' => $seguimiento->getRouteKey(),
        ...request()->query(),
    ])))->name('pasiva-lineas.index');

    Route::prefix('/{seguimiento}/proyectos/{proyecto}')->name('proyectos.')->group(function () {
        Route::get('/', fn (Seguimiento $seguimiento, Proyecto $proyecto) => redirect(Workspace::getUrl([
            'workspace' => 'seguimiento-proyecto-reportar',
            'record' => $seguimiento->getRouteKey().'-'.$proyecto->getRouteKey(),
            ...request()->query(),
        ])))->name('show');
        Route::get('/detalle', [ReporteProyectoController::class, 'detalle'])->name('detalle');
        Route::post('/techos', [TechoController::class, 'store'])->name('techos.store');
        Route::post('/actividades', [ReporteProyectoController::class, 'storeActividad'])->name('actividades.store');
        Route::put('/ejecucion', [ReporteProyectoController::class, 'updateEjecucion'])->name('ejecucion.update');
        Route::post('/actividades/{actividad}/avance', [ReporteProyectoController::class, 'storeAvance'])->name('avances.store');
        Route::put('/focalizacion', [ReporteProyectoController::class, 'updateFocalizacion'])->name('focalizacion.update');
        Route::post('/enviar', [ReporteProyectoController::class, 'enviar'])->name('enviar');
        Route::post('/aprobar', [ReporteProyectoController::class, 'aprobar'])->name('aprobar');
        Route::post('/devolver', [ReporteProyectoController::class, 'devolver'])->name('devolver');
    });
});
