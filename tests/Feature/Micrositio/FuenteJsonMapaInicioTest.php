<?php

use App\Services\Micrositio\Mapa\FuenteJsonMapaInicio;
use Illuminate\Support\Facades\File;

test('lee las cuatro capas y los 29 municipios con divipola válido', function () {
    $fuente = new FuenteJsonMapaInicio;

    expect($fuente->capas())->toHaveCount(4);
    expect($fuente->capa('IPM-2018')['valor_departamental'])->toBeNull();

    foreach ($fuente->capas() as $capa) {
        expect($capa['valores'])->toHaveCount(29);
        foreach ($capa['valores'] as $valor) {
            expect($valor['divipola'])->toMatch('/^50\d{3}$/');
        }
    }

    expect($fuente->geometriaMunicipios()['features'])->toHaveCount(29);
    expect($fuente->geometriaColombia()['type'])->toBe('FeatureCollection');
});

test('falla con un mensaje claro cuando el json es inválido', function () {
    $directorio = storage_path('framework/testing-mapa-invalido');
    File::ensureDirectoryExists($directorio);
    File::put($directorio.'/datos-mapa-inicio.json', '{"capas":[{"codigo":"X","unidad":"pesos","valores":[]}]}');

    expect(fn () => (new FuenteJsonMapaInicio($directorio))->capas())
        ->toThrow(InvalidArgumentException::class, 'unidad no válida');
});
