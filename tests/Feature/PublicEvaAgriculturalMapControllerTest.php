<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicEvaAgriculturalMapControllerTest extends TestCase
{
    public function test_returns_joined_municipal_choropleth_with_weighted_yield(): void
    {
        $this->fakeSources();

        $response = $this->getJson(route('geodata.eva-agricultural-map', [
            'year' => 2025,
            'crop' => 'Maíz',
            'metric' => 'yield',
        ]));

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=3600, public, stale-while-revalidate=21600')
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('metadata.dataset_id', 'uejq-wxrr')
            ->assertJsonPath('metadata.metric', 'yield')
            ->assertJsonPath('features.0.properties.codigo_dane', '50001')
            ->assertJsonPath('features.0.properties.municipio', 'Villavicencio')
            ->assertJsonPath('features.0.properties.area_sembrada', 12)
            ->assertJsonPath('features.0.properties.produccion', 25)
            ->assertJsonPath('features.0.properties.rendimiento', 2.5)
            ->assertJsonPath('features.0.properties.valor', 2.5)
            ->assertJsonPath('features.1.properties.valor', null)
            ->assertJsonCount(2, 'features');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'resource/uejq-wxrr.json')
            && $request['$where'] === 'c_digo_dane_departamento="50" and a_o="2025" and cultivo="Maíz"'
            && str_contains($request['$select'], 'sum(producci_n::number) as produccion'));
    }

    public function test_returns_422_for_an_unavailable_crop_without_calling_external_sources(): void
    {
        Cache::flush();
        Http::preventStrayRequests();

        $this->getJson(route('geodata.eva-agricultural-map', [
            'crop' => 'Maíz" or 1=1',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('crop')
            ->assertJsonPath('message', 'Seleccione un cultivo disponible en EVA para el Meta.');

        Http::assertNothingSent();
    }

    public function test_returns_503_when_official_boundaries_have_no_geometry(): void
    {
        Cache::flush();
        Http::preventStrayRequests();
        config()->set('investments.socrata_base_url', 'https://datos.test');
        config()->set('investments.meta_boundaries_url', 'https://dane.test/boundaries');
        Http::fake([
            'https://datos.test/resource/uejq-wxrr.json*' => Http::response([]),
            'https://dane.test/boundaries' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [],
            ]),
        ]);

        $this->getJson(route('geodata.eva-agricultural-map'))
            ->assertServiceUnavailable()
            ->assertExactJson([
                'message' => 'No fue posible construir la capa EVA con los límites municipales del Meta.',
            ]);
    }

    public function test_caches_the_same_filtered_map(): void
    {
        $this->fakeSources();

        $this->getJson(route('geodata.eva-agricultural-map'))->assertOk();
        $this->getJson(route('geodata.eva-agricultural-map'))->assertOk();

        Http::assertSentCount(2);
    }

    private function fakeSources(): void
    {
        Cache::flush();
        Http::preventStrayRequests();
        config()->set('investments.socrata_base_url', 'https://datos.test');
        config()->set('investments.meta_boundaries_url', 'https://dane.test/boundaries');
        Http::fake([
            'https://datos.test/resource/uejq-wxrr.json*' => Http::response([[
                'c_digo_dane_municipio' => '50001',
                'municipio' => 'Villavicencio',
                'area_sembrada' => '12.00',
                'area_cosechada' => '10.00',
                'produccion' => '25.00',
            ]]),
            'https://dane.test/boundaries' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'properties' => ['mpio_cdpmp' => '50001', 'mpio_cnmbr' => 'Villavicencio'],
                        'geometry' => ['type' => 'Polygon', 'coordinates' => []],
                    ],
                    [
                        'type' => 'Feature',
                        'properties' => ['mpio_cdpmp' => '50006', 'mpio_cnmbr' => 'Acacías'],
                        'geometry' => ['type' => 'Polygon', 'coordinates' => []],
                    ],
                ],
            ]),
        ]);
    }
}
