<?php

use App\Models\SiidAreaTematica;
use App\Models\SiidEje;
use App\Models\SiidIndicador;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\Micrositio\CatalogoSiidDePrueba;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    CatalogoSiidDePrueba::asegurar();
});

test('devuelve el desglose con etiquetas código y nombre', function () {
    $eje = SiidEje::query()->create(['codigo' => 'ECO', 'nombre' => 'Económico', 'orden' => 1]);
    $area = SiidAreaTematica::query()->create(['eje_id' => $eje->id, 'nombre' => 'Empleo', 'slug' => 'empleo']);
    SiidIndicador::query()->create([
        'codigo' => 'IE-010',
        'nombre' => 'Tasa de ocupación',
        'eje_id' => $eje->id,
        'area_tematica_id' => $area->id,
        'pestana_origen' => 'prueba',
        'activo' => true,
    ]);

    $this->getJson(route('micrositio.contadores.detalle', 'indicadores_sectoriales'))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('grupos.0.items.0.etiqueta', 'IE-010 — Tasa de ocupación');
});

test('un origen inválido responde 404', function () {
    $this->getJson(route('micrositio.contadores.detalle', 'inventado'))->assertNotFound();
});

test('el desglose de contadores tiene límite de peticiones', function () {
    RateLimiter::for('micrositio-contadores', fn () => Limit::perMinute(2)->by('prueba-contadores'));

    $this->getJson(route('micrositio.contadores.detalle', 'politicas_publicas'))->assertOk();
    $this->getJson(route('micrositio.contadores.detalle', 'politicas_publicas'))->assertOk();
    $this->getJson(route('micrositio.contadores.detalle', 'politicas_publicas'))->assertStatus(429);
});
