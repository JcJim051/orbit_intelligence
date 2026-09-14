<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicMetaMunicipalBoundariesTest extends TestCase
{
    public function test_it_proxies_and_caches_official_boundaries_for_public_viewers(): void
    {
        Cache::forget('geovisors:meta-municipal-boundaries:v1');
        Http::fake([
            '*' => Http::response([
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => ['mpio_cdpmp' => '50001', 'mpio_cnmbr' => 'Villavicencio'],
                    'geometry' => ['type' => 'Polygon', 'coordinates' => []],
                ]],
            ]),
        ]);

        $this->getJson(route('geodata.meta-municipal-boundaries'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=86400, public, stale-while-revalidate=604800')
            ->assertJsonPath('type', 'FeatureCollection')
            ->assertJsonPath('features.0.properties.mpio_cnmbr', 'Villavicencio');

        $this->getJson(route('geodata.meta-municipal-boundaries'))->assertOk();

        Http::assertSentCount(1);
    }
}
