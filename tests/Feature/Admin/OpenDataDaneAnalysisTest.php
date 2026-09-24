<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenDataDaneAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_detects_meta_municipality_codes_and_reports_duplicates(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/views/wxyz-9876')) {
                return Http::response(['name' => 'Indicadores municipales', 'columns' => [
                    ['fieldName' => 'codigo_dane_municipio', 'name' => 'Código DANE municipio', 'dataTypeName' => 'text'],
                    ['fieldName' => 'valor', 'name' => 'Valor', 'dataTypeName' => 'number'],
                ]]);
            }
            if (str_contains($request->url(), '/resource/wxyz-9876.json')) {
                if (($request->data()['$select'] ?? null) === 'count(*) as total') return Http::response([['total' => '2']]);
                if (isset($request->data()['$group'])) return Http::response([['codigo_dane_municipio' => '50001', 'siid_value' => '3']]);

                return Http::response([
                        ['codigo_dane_municipio' => '50001', 'valor' => '1'],
                        ['codigo_dane_municipio' => '50001', 'valor' => '2'],
                ]);
            }
            if (str_contains($request->url(), 'FeatureServer/317/query')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => [[
                    'type' => 'Feature',
                    'properties' => ['mpio_cdpmp' => '50001', 'mpio_cnmbr' => 'Villavicencio'],
                    'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-73, 4], [-72, 4], [-72, 5], [-73, 4]]]],
                ]]]);
            }

            return Http::response([], 404);
        });

        $user = User::factory()->create(['role' => UserRole::SiidManager]);
        $this->actingAs($user)->postJson(route('admin.open-data-sources.analyze'), [
            'url' => 'https://datos.gov.co/d/wxyz-9876',
        ])->assertOk()
            ->assertJsonPath('geography.mode', 'dane_municipality')
            ->assertJsonPath('geography.diagnostics.matched', 2)
            ->assertJsonPath('geography.diagnostics.duplicate_codes.0', '50001');

        $this->actingAs($user)->postJson(route('admin.open-data-sources.store'), [
            'url' => 'https://datos.gov.co/d/wxyz-9876', 'name' => 'Indicadores', 'slug' => 'indicadores',
            'attribution' => 'Datos.gov.co', 'metric_field' => 'valor', 'aggregation' => 'sum',
            'popup_fields' => ['codigo_dane_municipio', 'valor'], 'filters' => [], 'palette' => 'green',
            'no_data_color' => '#d1d5db', 'opacity' => .75, 'target' => 'new',
            'viewer_name' => 'Visor indicadores', 'viewer_slug' => 'visor-indicadores',
        ])->assertCreated();

        $this->actingAs($user)->getJson(route('admin.open-data-sources.preview', 'indicadores'))
            ->assertOk()
            ->assertJsonPath('features.0.properties.codigo_dane', '50001')
            ->assertJsonPath('features.0.properties.valor', 3);
    }
}
