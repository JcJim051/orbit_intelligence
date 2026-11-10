<?php

use App\Enums\UserRole;
use App\Models\FuenteFinanciacion;
use App\Models\User;
use App\Services\Analitica\DiccionarioAnalitica;
use App\Services\Analitica\EsquemaAnalitica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('las vistas del diccionario se pueden consultar y los códigos salen como código — nombre', function () {
    foreach (DiccionarioAnalitica::vistas() as $vista) {
        expect(DB::select('select * from '.EsquemaAnalitica::tabla($vista['nombre']).' limit 1'))->toBeArray();
    }

    FuenteFinanciacion::factory()->create([
        'codigo' => '20',
        'nombre' => 'Ingresos Corrientes de libre destinacion',
    ]);

    $fuente = DB::table(EsquemaAnalitica::tabla('v_fuentes_financiacion'))->where('codigo', '20')->first();

    expect($fuente->fuente)->toBe('20 — Ingresos Corrientes de libre destinacion');

    $pendientes = array_column(DiccionarioAnalitica::pendientes(), 'nombre');

    expect($pendientes)->toContain('Puntos críticos viales')
        ->toContain('Horas máquina')
        ->toContain('Pagos de pasiva por BPIN')
        ->and(array_column(DiccionarioAnalitica::vistas(), 'nombre'))->not->toContain('Puntos críticos viales');
});

test('el administrador abre el catálogo y quien no lo es recibe 403', function () {
    $miembro = User::factory()->create(['role' => UserRole::Member]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($miembro)->get('/gestion/catalogo-analitica')->assertForbidden();

    $this->actingAs($admin)->get('/gestion/catalogo-analitica')
        ->assertOk()
        ->assertSee('v_pasiva_vigente')
        ->assertSee('pasiva_sankey_fuente_sector')
        ->assertSee('Puntos críticos viales');
});

test('el comando registra en Superset las vistas que faltan y refresca las que ya existen', function () {
    config([
        'superset.url' => 'http://superset.test',
        'superset.username' => 'servicio',
        'superset.password' => 'secreto',
        'superset.database_id' => 7,
    ]);

    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/api/v1/security/login')) {
            return Http::response(['access_token' => 'acceso']);
        }

        if (str_contains($request->url(), '/api/v1/dataset/?')) {
            return Http::response(['result' => [
                ['id' => 3, 'schema' => 'analitica', 'table_name' => 'v_pasiva_vigente'],
            ]]);
        }

        if (str_contains($request->url(), '/refresh')) {
            return Http::response(['message' => 'ok']);
        }

        return Http::response(['id' => 9], 201);
    });

    $this->artisan('analitica:sincronizar-datasets')->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/v1/dataset/')
        && $request->data()['schema'] === 'analitica'
        && $request->data()['table_name'] === 'pasiva_sankey_fuente_sector'
        && $request->data()['database'] === 7);

    Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
        && str_contains($request->url(), '/api/v1/dataset/3/refresh'));
});

test('preparar el rol de lectura exige PostgreSQL', function () {
    $this->artisan('analitica:preparar-rol')->assertFailed();
});
