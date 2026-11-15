<?php

use App\Models\IndicadorTerritorial;
use App\Models\MicrositioCifra;
use App\Models\MicrositioPolitica;
use App\Models\SiidAreaTematica;
use App\Models\SiidEje;
use App\Models\SiidIndicador;
use App\Services\Micrositio\ContadoresMicrositio;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Micrositio\CatalogoSiidDePrueba;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    CatalogoSiidDePrueba::asegurar();
});

function ejeDePrueba(): SiidEje
{
    return SiidEje::query()->create(['codigo' => 'ECO', 'nombre' => 'Económico', 'orden' => 1]);
}

test('cuenta solo los indicadores sectoriales activos y los publicados cuando el criterio lo pide', function () {
    $eje = ejeDePrueba();
    $area = SiidAreaTematica::query()->create(['eje_id' => $eje->id, 'nombre' => 'Educación', 'slug' => 'educacion']);
    $activo = SiidIndicador::query()->create(['codigo' => 'IS-1', 'nombre' => 'Cobertura', 'eje_id' => $eje->id, 'area_tematica_id' => $area->id, 'pestana_origen' => 'prueba', 'activo' => true]);
    SiidIndicador::query()->create(['codigo' => 'IS-2', 'nombre' => 'Inactivo', 'eje_id' => $eje->id, 'area_tematica_id' => $area->id, 'pestana_origen' => 'prueba', 'activo' => false]);
    $publicado = SiidIndicador::query()->create(['codigo' => 'IS-3', 'nombre' => 'Publicado', 'eje_id' => $eje->id, 'area_tematica_id' => $area->id, 'pestana_origen' => 'prueba', 'activo' => true]);
    $publicado->publicacion()->create(['visible' => true, 'orden' => 1]);
    $activo->publicacion()->create(['visible' => false, 'orden' => 2]);
    $contadores = app(ContadoresMicrositio::class);

    expect($contadores->valor('indicadores_sectoriales', 'activos'))->toBe(2)
        ->and($contadores->valor('indicadores_sectoriales', 'publicados_micrositio'))->toBe(1);

    $grupo = $contadores->desglose('indicadores_sectoriales', 'activos')[0];
    expect($grupo['grupo'])->toBe('Económico — Educación')
        ->and($grupo['items'][0]['etiqueta'])->toBe('IS-1 — Cobertura');
});

test('cuenta indicadores territoriales activos y excluye los eliminados', function () {
    IndicadorTerritorial::query()->create(['codigo' => 'IT-1', 'nombre' => 'Vigente', 'activo' => true, 'estado' => 'activo', 'area_tematica' => 'Salud']);
    $borrado = IndicadorTerritorial::query()->create(['codigo' => 'IT-2', 'nombre' => 'Borrado', 'activo' => true, 'estado' => 'activo']);
    IndicadorTerritorial::query()->create(['codigo' => 'IT-3', 'nombre' => 'Inactivo', 'activo' => false, 'estado' => 'precargado']);
    $borrado->delete();

    expect(app(ContadoresMicrositio::class)->valor('indicadores_territoriales', 'activos'))->toBe(1);
    expect(app(ContadoresMicrositio::class)->desglose('indicadores_territoriales', 'activos')[0]['items'][0]['etiqueta'])->toBe('IT-1 — Vigente');
});

test('cuenta políticas publicadas y excluye los ejemplos', function () {
    MicrositioPolitica::factory()->create(['nombre' => 'Infancia', 'es_ejemplo' => false]);
    MicrositioPolitica::factory()->create(['nombre' => 'Ejemplo', 'es_ejemplo' => true]);
    app(PublicadorMicrositio::class)->publicar('politicas');

    expect(app(ContadoresMicrositio::class)->valor('politicas_publicas', 'activos'))->toBe(1);
    expect(app(ContadoresMicrositio::class)->desglose('politicas_publicas', 'activos')[0]['items'][0]['etiqueta'])->toBe('Infancia');
});

test('reutiliza la caché y la invalida al publicar o al guardar un indicador', function () {
    $eje = ejeDePrueba();
    $indicador = SiidIndicador::query()->create(['codigo' => 'IS-9', 'nombre' => 'Uno', 'eje_id' => $eje->id, 'pestana_origen' => 'prueba', 'activo' => true]);
    $contadores = app(ContadoresMicrositio::class);
    expect($contadores->valor('indicadores_sectoriales', 'activos'))->toBe(1);

    DB::flushQueryLog();
    DB::enableQueryLog();
    expect($contadores->valor('indicadores_sectoriales', 'activos'))->toBe(1);
    expect(DB::getQueryLog())->toBeEmpty();

    app(PublicadorMicrositio::class)->publicar('cifras');
    DB::flushQueryLog();
    expect($contadores->valor('indicadores_sectoriales', 'activos'))->toBe(1);
    expect(DB::getQueryLog())->not->toBeEmpty();

    $indicador->update(['activo' => false]);
    expect($contadores->valor('indicadores_sectoriales', 'activos'))->toBe(0);
});

test('el origen manual y una fuente fallida usan el valor de respaldo', function () {
    MicrositioCifra::factory()->create([
        'valor' => '77',
        'origen' => 'manual',
        'etiqueta' => 'Manual',
        'es_ejemplo' => false,
    ]);
    app(PublicadorMicrositio::class)->publicar('cifras');

    expect(app(ContadoresMicrositio::class)->valor('manual', 'activos'))->toBeNull();
    $this->get(route('micrositio.inicio'))->assertOk()->assertSee('77');

    $this->mock(ContadoresMicrositio::class, function ($mock): void {
        $mock->shouldReceive('valor')->andReturn(null);
    });
    MicrositioCifra::query()->delete();
    app(PublicadorMicrositio::class)->publicar('cifras');
    MicrositioCifra::factory()->create([
        'valor' => '41',
        'origen' => 'indicadores_sectoriales',
        'etiqueta' => 'Respaldo',
        'es_ejemplo' => false,
    ]);
    app(PublicadorMicrositio::class)->publicar('cifras');

    $this->get(route('micrositio.inicio'))->assertOk()->assertSee('41')->assertDontSee('data-contador', false);
});
