<?php

use App\Models\MicrositioMapa;
use App\Models\MicrositioMapaCapa;
use App\Services\Micrositio\Mapa\FuenteJsonMapaInicio;
use App\Services\Micrositio\PublicadorMicrositio;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $base = (new FuenteJsonMapaInicio)->capa('POB-2026');
    foreach ([
        ['POB-2026', 'Población 2026', 'habitantes', 10],
        ['PETROLEO', 'Petróleo 03/2026', 'barriles_mes', 20],
        ['AGRO-PROD', 'Producción agrícola 2025', 'toneladas', 30],
    ] as [$codigo, $rotulo, $unidad, $orden]) {
        MicrositioMapaCapa::query()->create([
            'codigo' => $codigo,
            'nombre' => $rotulo,
            'rotulo_corto' => $rotulo,
            'unidad' => $unidad,
            'unidad_etiqueta' => $rotulo,
            'periodo' => $codigo === 'PETROLEO' ? 'Marzo 2026' : ($codigo === 'AGRO-PROD' ? '2025' : '2026'),
            'periodo_orden' => 202600,
            'fuente' => 'Fuente oficial',
            'fuente_url' => 'https://datos.test/fuente',
            'polaridad' => 'neutral',
            'valores' => $base['valores'],
            'checksum' => hash('sha256', $codigo),
            'estado' => 'lista',
            'activa' => true,
            'orden' => $orden,
        ]);
    }
});

test('datos json solo incluye la capa principal y las adicionales publicadas', function () {
    MicrositioMapa::query()->create([
        'mostrar_bloque' => true,
        'capa_principal' => 'POB-2026',
        'capas_adicionales' => ['PETROLEO', 'AGRO-PROD'],
        'paleta' => 'auto',
        'polaridad' => 'auto',
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
    expect($codigos)->toBe(['POB-2026', 'PETROLEO', 'AGRO-PROD']);
    expect(collect($respuesta->json('capas'))->pluck('valores')->map(fn (array $valores): int => count($valores))->all())->toBe([29, 29, 29]);
    expect($respuesta->json('capas.1.unidad'))->toBe('barriles_mes');
    expect($respuesta->json('capas.2.unidad'))->toBe('toneladas');
    expect($respuesta->json('capas.1'))->toHaveKeys([
        'fuente_dataset_id', 'fuente_actualizada_en', 'sincronizada_en', 'unidad_etiqueta', 'estado_frescura',
    ]);
});

test('los geojson del mapa son colecciones válidas', function () {
    $municipios = $this->get(route('micrositio.mapa.municipios'));
    $colombia = $this->get(route('micrositio.mapa.colombia'));

    $municipios->assertOk()->assertHeader('Content-Type', 'application/json; charset=UTF-8');
    expect($municipios->json('type'))->toBe('FeatureCollection')
        ->and($municipios->json('features'))->toHaveCount(29)
        ->and($colombia->json('type'))->toBe('FeatureCollection');
});
