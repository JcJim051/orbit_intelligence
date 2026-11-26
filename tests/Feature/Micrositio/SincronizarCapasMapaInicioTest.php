<?php

use App\Models\MicrositioMapaCapa;
use App\Models\MicrositioMapaSincronizacion;
use App\Services\Micrositio\Mapa\SincronizarCapasMapaInicio;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('micrositio_mapa.base_url', 'https://datos.test');
    Http::preventStrayRequests();
});

function metadataMapa(array $campos, int $actualizado = 1793404800): array
{
    return [
        'rowsUpdatedAt' => $actualizado,
        'columns' => array_map(fn (string $campo): array => ['fieldName' => $campo], $campos),
    ];
}

function respuestasMapaExitosas(): void
{
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'api/views/fdvb-hsrf')) {
            return Http::response(metadataMapa(['vigencia', 'mes', 'nombre_mes', 'departamento', 'municipio', 'produccion_bls']));
        }
        if (str_contains($request->url(), 'api/views/uejq-wxrr')) {
            return Http::response(metadataMapa(['c_digo_dane_departamento', 'c_digo_dane_municipio', 'municipio', 'a_o', 'producci_n']));
        }
        if (str_contains($request->url(), 'fdvb-hsrf.json')) {
            if (str_contains((string) $request['$select'], 'nombre_mes')) {
                return Http::response([['vigencia' => '2026', 'mes' => '3', 'nombre_mes' => 'Marzo']]);
            }

            return Http::response([
                ['municipio' => 'PUERTO GAITAN', 'produccion_bls' => '1,500.50'],
                ['municipio' => 'PUERTO GAITAN', 'produccion_bls' => '499.50'],
                ['municipio' => 'VILLAVICENCIO', 'produccion_bls' => '25.00'],
                ['municipio' => 'CASTILLA NUEVA', 'produccion_bls' => '10.00'],
            ]);
        }
        if (str_contains($request->url(), 'uejq-wxrr.json')) {
            if (str_contains((string) $request['$select'], 'max(a_o)')) {
                return Http::response([['periodo' => '2025']]);
            }

            return Http::response([
                ['c_digo_dane_municipio' => '50001', 'municipio' => 'Villavicencio', 'valor' => '125.50'],
                ['c_digo_dane_municipio' => '50568', 'municipio' => 'Puerto Gaitán', 'valor' => '900.25'],
            ]);
        }

        return Http::response(['message' => 'Ruta no simulada'], 500);
    });
}

test('sincroniza las tres capas y completa los municipios sin actividad con cero', function () {
    respuestasMapaExitosas();

    $resultado = app(SincronizarCapasMapaInicio::class)->sincronizar();

    expect($resultado['petroleo']['estado'])->toBe('exitosa')
        ->and($resultado['agricultura']['estado'])->toBe('exitosa')
        ->and(MicrositioMapaCapa::query()->count())->toBe(3)
        ->and(MicrositioMapaSincronizacion::query()->where('estado', 'exitosa')->count())->toBe(2);

    $petroleo = MicrositioMapaCapa::query()->where('codigo', 'PETROLEO')->firstOrFail();
    $agricultura = MicrositioMapaCapa::query()->where('codigo', 'AGRO-PROD')->firstOrFail();
    expect($petroleo->periodo)->toBe('Marzo 2026')
        ->and($petroleo->valores)->toHaveCount(29)
        ->and($petroleo->valor_departamental)->toBe(2035.0)
        ->and(collect($petroleo->valores)->firstWhere('divipola', '50568')['valor'])->toEqual(2000.0)
        ->and(collect($petroleo->valores)->firstWhere('divipola', '50006')['valor'])->toEqual(0.0)
        ->and($agricultura->periodo)->toBe('2025')
        ->and($agricultura->valores)->toHaveCount(29)
        ->and($agricultura->valor_departamental)->toBe(1025.75);
});

test('conserva la última instantánea válida cuando la fuente falla', function () {
    respuestasMapaExitosas();
    $sincronizador = app(SincronizarCapasMapaInicio::class);
    $sincronizador->sincronizar('petroleo');
    $antes = MicrositioMapaCapa::query()->where('codigo', 'PETROLEO')->firstOrFail();
    $checksum = $antes->checksum;
    $valores = $antes->valores;

    Http::swap(new Factory(app('events')));
    Http::preventStrayRequests();
    Http::fake(['https://datos.test/*' => Http::response(['message' => 'caída'], 503)]);
    $resultado = app(SincronizarCapasMapaInicio::class)->sincronizar('petroleo');
    $despues = $antes->fresh();

    expect($resultado['petroleo']['estado'])->toBe('fallida')
        ->and($despues->checksum)->toBe($checksum)
        ->and($despues->valores)->toBe($valores)
        ->and($despues->estado)->toBe('error')
        ->and($despues->ultimo_error)->not->toBeEmpty()
        ->and(MicrositioMapaSincronizacion::query()->where('estado', 'fallida')->count())->toBe(1);
});

test('rechaza cambios en el esquema oficial antes de reemplazar datos', function () {
    Http::fake([
        'https://datos.test/api/views/fdvb-hsrf' => Http::response(metadataMapa(['vigencia', 'mes'])),
    ]);

    $resultado = app(SincronizarCapasMapaInicio::class)->sincronizar('petroleo');

    expect($resultado['petroleo']['estado'])->toBe('fallida')
        ->and($resultado['petroleo']['error'])->toContain('estructura oficial cambió')
        ->and(MicrositioMapaCapa::query()->where('codigo', 'PETROLEO')->exists())->toBeFalse();
});

test('conserva los datos publicados ante respuestas 429, 500 o timeout', function (Closure $respuesta) {
    respuestasMapaExitosas();
    app(SincronizarCapasMapaInicio::class)->sincronizar('petroleo');
    $antes = MicrositioMapaCapa::query()->where('codigo', 'PETROLEO')->firstOrFail();

    Http::swap(new Factory(app('events')));
    Http::preventStrayRequests();
    Http::fake(fn () => $respuesta());

    $resultado = app(SincronizarCapasMapaInicio::class)->sincronizar('petroleo');
    $despues = $antes->fresh();

    expect($resultado['petroleo']['estado'])->toBe('fallida')
        ->and($despues->checksum)->toBe($antes->checksum)
        ->and($despues->valores)->toBe($antes->valores);
})->with([
    'límite 429' => fn () => Http::response(['message' => 'rate limit'], 429),
    'error 500' => fn () => Http::response(['message' => 'server error'], 500),
    'timeout' => fn () => Http::failedConnection('Connection timed out'),
]);

test('rechaza municipios de petróleo que no se puedan homologar', function () {
    Http::fake(function (Request $request) {
        if (str_contains($request->url(), 'api/views/fdvb-hsrf')) {
            return Http::response(metadataMapa(['vigencia', 'mes', 'nombre_mes', 'departamento', 'municipio', 'produccion_bls']));
        }
        if (str_contains((string) $request['$select'], 'nombre_mes')) {
            return Http::response([['vigencia' => '2026', 'mes' => '3', 'nombre_mes' => 'Marzo']]);
        }

        return Http::response([['municipio' => 'MUNICIPIO DESCONOCIDO', 'produccion_bls' => '10']]);
    });

    $resultado = app(SincronizarCapasMapaInicio::class)->sincronizar('petroleo');

    expect($resultado['petroleo']['estado'])->toBe('fallida')
        ->and($resultado['petroleo']['error'])->toContain('no pudieron homologarse')
        ->and(MicrositioMapaCapa::query()->where('codigo', 'PETROLEO')->exists())->toBeFalse();
});

test('el comando programado usa un bloqueo para evitar solapamientos', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn (object $evento): bool => str_contains((string) $evento->command, 'micrositio:sincronizar-mapa-inicio'));

    expect($evento)->not->toBeNull()
        ->and($evento->withoutOverlapping)->toBeTrue();
});
