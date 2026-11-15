<?php

use App\Models\MicrositioMapa;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('datos json solo incluye la capa principal y las adicionales publicadas', function () {
    MicrositioMapa::query()->create([
        'mostrar_bloque' => true,
        'capa_principal' => 'IPM-2018',
        'capas_adicionales' => ['NBI-2018'],
        'paleta' => 'semaforo',
        'polaridad' => 'alto_malo',
    ]);
    app(PublicadorMicrositio::class)->publicar('mapa');

    $respuesta = $this->get(route('micrositio.mapa.datos'));

    $respuesta->assertOk();
    expect($respuesta->headers->get('Cache-Control'))
        ->toContain('public')
        ->toContain('max-age=86400')
        ->toContain('stale-while-revalidate=604800');
    expect($respuesta->headers->get('ETag'))->not->toBeEmpty();
    $codigos = collect($respuesta->json('capas'))->pluck('codigo')->all();
    expect($codigos)->toBe(['IPM-2018', 'NBI-2018']);
    expect($respuesta->json('capas.0.valor_departamental'))->toBeNull();
});

test('los geojson del mapa son colecciones válidas', function () {
    $municipios = $this->get(route('micrositio.mapa.municipios'));
    $colombia = $this->get(route('micrositio.mapa.colombia'));

    $municipios->assertOk()->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    expect($municipios->json('type'))->toBe('FeatureCollection')
        ->and($municipios->json('features'))->toHaveCount(29)
        ->and($colombia->json('type'))->toBe('FeatureCollection');
});
