<?php

use App\Models\AnaliticaSincronizacion;
use App\Models\PasivaCarga;
use App\Models\PasivaRubro;
use App\Models\Seguimiento;
use App\Models\User;
use App\Services\Analitica\EsquemaAnalitica;
use App\Services\Analitica\ImportadorPasivaAnalitica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function pasivaFixture(string $sufijo = ''): string
{
    return (string) file_get_contents(base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.csv')).$sufijo;
}

function cargarPasivaVigente(string $corte, string $contenido, string $nombre): PasivaCarga
{
    $ruta = 'pasivas/'.$nombre;
    Storage::disk('local')->put($ruta, $contenido);
    $fecha = Carbon::parse($corte);

    $seguimiento = Seguimiento::factory()->create([
        'vigencia' => (int) $fecha->year,
        'mes' => (int) $fecha->month,
        'fecha_corte' => $corte,
    ]);

    return PasivaCarga::query()->create([
        'seguimiento_id' => $seguimiento->id,
        'disk' => 'local',
        'path' => $ruta,
        'nombre_original' => $nombre,
        'mime' => 'text/csv',
        'bytes' => strlen($contenido),
        'sha256' => hash('sha256', $contenido),
        'base_techo' => 'apropiacion_definitiva',
        'es_vigente' => true,
        'uploaded_by' => User::factory()->create()->id,
    ]);
}

test('una pasiva que queda vigente actualiza analitica y el registro del panel', function () {
    cargarPasivaVigente('2026-08-31', pasivaFixture(), 'agosto.csv');

    $vigente = DB::table(EsquemaAnalitica::tabla('v_pasiva_corte_vigente'))->first();

    expect(PasivaRubro::query()->count())->toBeGreaterThan(0)
        ->and($vigente->fecha_corte)->toBe('2026-08-31')
        ->and($vigente->origen)->toBe('Secretaría de Hacienda — agosto.csv')
        ->and(AnaliticaSincronizacion::query()->where('estado', 'ok')->count())->toBe(1);
});

test('una carga de un corte más reciente reemplaza la vista vigente y conserva el corte anterior', function () {
    cargarPasivaVigente('2026-07-31', pasivaFixture(), 'julio.csv');
    $anteriores = PasivaRubro::query()->whereDate('corte', '2026-07-31')->count();

    cargarPasivaVigente('2026-08-31', pasivaFixture("\n"), 'agosto.csv');

    $vigente = DB::table(EsquemaAnalitica::tabla('v_pasiva_vigente'))->first();

    expect($anteriores)->toBeGreaterThan(0)
        ->and(PasivaRubro::query()->whereDate('corte', '2026-07-31')->count())->toBe($anteriores)
        ->and($vigente->fecha_corte)->toBe('2026-08-31')
        ->and(PasivaRubro::query()->distinct()->count('sha256'))->toBe(2);
});

test('volver a cargar el mismo archivo para el mismo corte no duplica rubros', function () {
    $ruta = base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.csv');
    $importador = app(ImportadorPasivaAnalitica::class);
    $primero = $importador->importar($ruta, 'csv', '2026-08-31');
    $antes = PasivaRubro::query()->count();

    $segundo = $importador->importar($ruta, 'csv', '2026-08-31');

    expect($segundo->estado)->toBe('omitida')
        ->and($segundo->rubros)->toBe($primero->rubros)
        ->and(PasivaRubro::query()->count())->toBe($antes)
        ->and(AnaliticaSincronizacion::query()->where('estado', 'omitida')->count())->toBe(1);
});

test('una columna desconocida del archivo no cambia las columnas de la vista vigente', function () {
    $original = pasivaFixture();
    $lineas = preg_split("/\r\n|\n|\r/", $original) ?: [];
    $lineas[0] .= ';nota_extra';
    $ruta = tempnam(sys_get_temp_dir(), 'pasiva').'.csv';
    file_put_contents($ruta, implode("\n", $lineas));

    app(ImportadorPasivaAnalitica::class)->importar($ruta, 'csv', '2026-08-31');
    $fila = DB::table(EsquemaAnalitica::tabla('v_pasiva_vigente'))->first();

    expect(array_key_exists('fecha_corte', (array) $fila))->toBeTrue()
        ->and(array_key_exists('origen', (array) $fila))->toBeTrue()
        ->and(array_key_exists('nota_extra', (array) $fila))->toBeFalse();
});
