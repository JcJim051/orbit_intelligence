<?php

use App\Http\Controllers\MicrositioController;
use App\Http\Controllers\MicrositioExcelController;
use App\Http\Controllers\MicrositioSiidController;
use App\Http\Controllers\PasivaAnaliticaController;
use App\Http\Controllers\SupersetGuestTokenController;
use App\Http\Middleware\SiidEmbedSecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::prefix('micrositio')->name('micrositio.')->group(function () {
    Route::get('/', [MicrositioController::class, 'inicio'])->name('inicio');
    Route::get('/politicas-publicas', [MicrositioController::class, 'politicas'])->name('politicas');
    Route::get('/politicas-publicas/{slug}/documentos/{indice}', [MicrositioController::class, 'documentoPolitica'])->whereNumber('indice')->name('politicas.documento');
    Route::get('/indicadores', [MicrositioSiidController::class, 'index'])->name('indicadores');
    Route::get('/indicadores/{codigo}', [MicrositioSiidController::class, 'legacy'])->name('indicadores.legacy');
    Route::get('/indicadores/{codigo}/datos.csv', [MicrositioController::class, 'csv'])->name('indicadores.csv');
    Route::get('/indicadores/{codigo}/token', [SupersetGuestTokenController::class, 'publico'])
        ->middleware('throttle:superset-guest')
        ->name('indicadores.token');
    Route::get('/datos-abiertos/pasiva.csv', [MicrositioController::class, 'datosAbiertos'])->name('datos-abiertos');
    Route::get('/publicaciones/{publicacion}/archivo', [MicrositioController::class, 'archivo'])->whereNumber('publicacion')->name('publicaciones.archivo');
    Route::get('/publicaciones', [MicrositioController::class, 'publicaciones'])->name('publicaciones');
    Route::get('/banner', [MicrositioController::class, 'banner'])->name('banner');
    Route::middleware(SiidEmbedSecurityHeaders::class)->group(function (): void {
        Route::get('/siid', [MicrositioSiidController::class, 'index'])->name('siid.index');
        Route::get('/siid/indicadores/{publicacion}', [MicrositioSiidController::class, 'show'])->whereNumber('publicacion')->name('siid.show');
        Route::get('/siid/indicadores/{publicacion}/token', [MicrositioSiidController::class, 'token'])->whereNumber('publicacion')->middleware('throttle:superset-guest')->name('siid.token');
    });
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/micrositio/tableros/{codigo}/token', [SupersetGuestTokenController::class, 'autenticado'])
        ->middleware('throttle:superset-guest')
        ->name('micrositio.tableros.token');
    Route::post('/analitica/pasiva', [PasivaAnaliticaController::class, 'store'])
        ->middleware('throttle:uploads')
        ->name('analitica.pasiva.store');
    Route::get('/gestion/micrositio/{seccion}/exportar', [MicrositioExcelController::class, 'exportar'])->name('micrositio.excel.exportar');
    Route::get('/gestion/micrositio/{seccion}/plantilla', [MicrositioExcelController::class, 'plantilla'])->name('micrositio.excel.plantilla');
    Route::post('/gestion/micrositio/{seccion}/importar', [MicrositioExcelController::class, 'importar'])->name('micrositio.excel.importar');
});
