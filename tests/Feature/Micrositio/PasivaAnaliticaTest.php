<?php

use App\Enums\UserRole;
use App\Models\PasivaRubro;
use App\Models\User;
use App\Services\Analitica\ClasificadorFuentePasiva;
use App\Services\Analitica\EsquemaAnalitica;
use App\Services\Analitica\ImportadorPasivaAnalitica;
use App\Services\Analitica\LectorPasivaHacienda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('agrupa las fuentes del catálogo PCT como la pasiva de Hacienda', function () {
    $clasificador = app(ClasificadorFuentePasiva::class);

    expect($clasificador->grupo('20'))->toBe('Recursos propios')
        ->and($clasificador->grupo('00AD'))->toBe('SGR')
        ->and($clasificador->grupo('25'))->toBe('SGP')
        ->and($clasificador->grupo('125'))->toBe('Crédito')
        ->and($clasificador->grupo('25E'))->toBe('Otras de la Nación')
        ->and($clasificador->grupo('ZZ9'))->toBe('Sin catálogo')
        ->and($clasificador->etiqueta('ZZ9'))->toBe('ZZ9 — Sin catálogo')
        ->and($clasificador->etiqueta('20'))->toBe('20 — Ingresos Corrientes de libre destinacion');
});

test('lee la pasiva de prueba y cruza sector, bpin y fuente', function () {
    $filas = app(LectorPasivaHacienda::class)->leer(
        base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.csv'),
        'csv',
    );

    $propios = collect($filas)->first(fn (array $fila): bool => $fila['fuente_codigo'] === '20' && $fila['es_inversion'] === 'Inversión' && str_contains($fila['bpin'], '2024005500096'));
    $sinCatalogo = collect($filas)->firstWhere('fuente_codigo', 'ZZ9');
    $sgr = collect($filas)->firstWhere('fuente_codigo', '00AD');
    $sinBpin = collect($filas)->firstWhere('bpin', 'Sin BPIN');

    expect($propios['grupo_fuente'])->toBe('Recursos propios')
        ->and($propios['sector'])->toBe('04 — Información estadística')
        ->and($propios['fuente'])->toBe('20 — Ingresos Corrientes de libre destinacion')
        ->and($propios['definitiva'])->toBe(300000000.0)
        ->and($sinCatalogo['grupo_fuente'])->toBe('Sin catálogo')
        ->and($sgr['grupo_fuente'])->toBe('SGR')
        ->and($sinBpin['es_inversion'])->toBe('Inversión');
});

test('lee la grilla posicional del excel de Hacienda', function () {
    $ruta = tempnam(sys_get_temp_dir(), 'pasiva').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($ruta);

    foreach (range(1, 7) as $numero) {
        $writer->addRow(Row::fromValues(['Título '.$numero]));
    }

    $sector = array_fill(0, 19, null);
    $sector[0] = '0301 - 2.3.22';
    $sector[1] = 'SECTOR: EDUCACIÓN';
    $writer->addRow(Row::fromValues($sector));

    $detalle = array_fill(0, 19, null);
    $detalle[0] = '0301 - 2.3.22.1 - 25';
    $detalle[1] = 'Servicios educativos';
    $detalle[11] = 15000000;
    $detalle[12] = 0;
    $detalle[14] = 0;
    $detalle[16] = 0;
    $detalle[18] = 0;
    $writer->addRow(Row::fromValues($detalle));
    $writer->close();

    $filas = app(LectorPasivaHacienda::class)->leer($ruta, 'xlsx');

    expect($filas)->toHaveCount(1)
        ->and($filas[0]['grupo_fuente'])->toBe('SGP')
        ->and($filas[0]['fuente'])->toBe('25 — SGP - Educacion - Prestacion CSF')
        ->and($filas[0]['sector'])->toBe('22 — Educación')
        ->and($filas[0]['bpin'])->toBe('Sin BPIN')
        ->and($filas[0]['definitiva'])->toBe(15000000.0);
});

test('carga la pasiva en el esquema analitica y la expone en una vista de solo lectura', function () {
    $total = app(ImportadorPasivaAnalitica::class)->importar(
        base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.csv'),
        'csv',
        '2026-08-31',
    );

    expect($total)->toBeGreaterThan(0)
        ->and(PasivaRubro::query()->where('grupo_fuente', 'Sin catálogo')->count())->toBe(1);

    $vista = DB::table(EsquemaAnalitica::tabla('v_pasiva_por_grupo_fuente'))
        ->where('grupo_fuente', 'SGR')
        ->first();

    expect($vista)->not->toBeNull()
        ->and((int) $vista->rubros)->toBeGreaterThan(0);
});

test('un visitante no carga la pasiva y un administrador sí', function () {
    $archivo = UploadedFile::fake()->createWithContent(
        'pasiva.csv',
        (string) file_get_contents(base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.csv')),
    );

    $this->post(route('analitica.pasiva.store'), [
        'archivo' => $archivo,
        'corte' => '2026-08-31',
    ])->assertRedirect(route('login'));

    $miembro = User::factory()->create();
    $this->actingAs($miembro)->post(route('analitica.pasiva.store'), [
        'archivo' => $archivo,
        'corte' => '2026-08-31',
    ])->assertForbidden();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin)->post(route('analitica.pasiva.store'), [
        'archivo' => UploadedFile::fake()->createWithContent(
            'pasiva.csv',
            (string) file_get_contents(base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.csv')),
        ),
        'corte' => '2026-08-31',
    ])->assertRedirect();

    expect(PasivaRubro::query()->count())->toBeGreaterThan(0);
});
