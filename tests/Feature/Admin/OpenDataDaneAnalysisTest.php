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
                if (($request->data()['$select'] ?? null) === 'count(*) as total') {
                    return Http::response([['total' => '2']]);
                }
                if (isset($request->data()['$group'])) {
                    return Http::response([['codigo_dane_municipio' => '50001', 'siid_value' => '3']]);
                }

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

        $user = User::factory()->create(['role' => UserRole::Admin]);
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

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/resource/wxyz-9876.json')
            && isset($request->data()['$group'])
            && str_contains((string) ($request->data()['$where'] ?? ''), "codigo_dane_municipio between '50000' and '50999'"));
    }

    public function test_analysis_detects_meta_codes_in_a_national_dataset_even_when_the_initial_sample_is_from_other_departments(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/views/uejq-wxrr')) {
                return Http::response(['name' => 'Evaluaciones Agropecuarias Municipales', 'columns' => [
                    ['fieldName' => 'c_digo_dane_departamento', 'name' => 'Código Dane departamento', 'dataTypeName' => 'text'],
                    ['fieldName' => 'c_digo_dane_municipio', 'name' => 'Código Dane municipio', 'dataTypeName' => 'text'],
                    ['fieldName' => 'producci_n', 'name' => 'Producción', 'dataTypeName' => 'number'],
                ]]);
            }
            if (str_contains($request->url(), '/resource/uejq-wxrr.json')) {
                if (($request->data()['$select'] ?? null) === 'count(*) as total') {
                    return Http::response([['total' => '166732']]);
                }
                if (str_contains((string) ($request->data()['$where'] ?? ''), "c_digo_dane_municipio between '50000' and '50999'")) {
                    return Http::response([
                        ['c_digo_dane_municipio' => '50001'],
                        ['c_digo_dane_municipio' => '50006'],
                    ]);
                }

                return Http::response([
                    ['c_digo_dane_departamento' => '05', 'c_digo_dane_municipio' => '05001', 'producci_n' => '10'],
                    ['c_digo_dane_departamento' => '05', 'c_digo_dane_municipio' => '05002', 'producci_n' => '20'],
                ]);
            }
            if (str_contains($request->url(), 'FeatureServer/317/query')) {
                return Http::response(['type' => 'FeatureCollection', 'features' => [
                    ['type' => 'Feature', 'properties' => ['mpio_cdpmp' => '50001', 'mpio_cnmbr' => 'Villavicencio'], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-73, 4], [-72, 4], [-72, 5], [-73, 4]]]]],
                    ['type' => 'Feature', 'properties' => ['mpio_cdpmp' => '50006', 'mpio_cnmbr' => 'Acacías'], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-74, 3], [-73, 3], [-73, 4], [-74, 3]]]]],
                ]]);
            }

            return Http::response([], 404);
        });

        $user = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($user)->postJson(route('admin.open-data-sources.analyze'), [
            'url' => 'https://www.datos.gov.co/api/v3/views/uejq-wxrr/query.json',
        ])->assertOk()
            ->assertJsonPath('geography.mode', 'dane_municipality')
            ->assertJsonPath('geography.dane_field', 'c_digo_dane_municipio')
            ->assertJsonPath('geography.diagnostics.sampled', 2)
            ->assertJsonPath('geography.diagnostics.matched', 2);
    }
}
