<?php

namespace Tests\Feature\Investment;

use App\Models\InvestmentFinancial;
use App\Models\InvestmentLocation;
use App\Models\InvestmentProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvestmentDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_sees_weighted_financial_totals_for_selected_universe(): void
    {
        $user = User::factory()->create();
        $included = InvestmentProject::factory()->create(['is_governor_meta' => true, 'is_territory_meta' => false, 'current_value' => null, 'paid_value' => null]);
        InvestmentProject::factory()->create(['is_governor_meta' => false, 'is_territory_meta' => true]);
        InvestmentFinancial::create([
            'investment_project_id' => $included->id, 'source_dataset_id' => 'v4ap-cvae', 'source_row_hash' => str_repeat('a', 64),
            'fiscal_year' => 2026, 'current_value' => 200, 'committed_value' => 150, 'obligated_value' => 100,
            'paid_value' => 50, 'raw_data' => [],
        ]);

        $response = $this->actingAs($user)->get(route('investments.dashboard', ['universe' => 'governor', 'year' => 2026]));

        $response->assertOk()->assertSee('25,0%')->assertSee('Gobernación del Meta')->assertSee('1');
    }

    public function test_portfolio_filters_projects_by_meta_municipality_without_duplicating_projects(): void
    {
        $user = User::factory()->create();
        $project = InvestmentProject::factory()->create(['name' => 'Proyecto visible', 'is_territory_meta' => true]);
        InvestmentLocation::create([
            'investment_project_id' => $project->id, 'source_dataset_id' => 'xikz-44ja', 'source_row_hash' => str_repeat('b', 64),
            'department_code' => '50', 'department' => 'Meta', 'municipality_code' => '50001', 'municipality' => 'Villavicencio', 'raw_data' => [],
        ]);
        InvestmentLocation::create([
            'investment_project_id' => $project->id, 'source_dataset_id' => 'xikz-44ja', 'source_row_hash' => str_repeat('c', 64),
            'department_code' => '50', 'department' => 'Meta', 'municipality_code' => '50001', 'municipality' => 'Villavicencio', 'raw_data' => ['scope' => 'second'],
        ]);

        $response = $this->actingAs($user)->get(route('investments.projects.index', [
            'universe' => 'territory',
            'period_mode' => 'horizon',
            'municipality' => '50001',
        ]));

        $response->assertOk()->assertSee('Proyecto visible');
        $this->assertSame(1, $response->viewData('projects')->total());
    }

    public function test_portfolio_filters_by_funding_source(): void
    {
        $user = User::factory()->create();
        $matching = InvestmentProject::factory()->create(['name' => 'Proyecto con regalías']);
        $other = InvestmentProject::factory()->create(['name' => 'Proyecto con recursos propios']);

        foreach ([[$matching, 'SGR', 'd'], [$other, 'Recursos propios', 'e']] as [$project, $source, $hash]) {
            InvestmentFinancial::create([
                'investment_project_id' => $project->id,
                'source_dataset_id' => 'v4ap-cvae',
                'source_row_hash' => str_repeat($hash, 64),
                'fiscal_year' => 2026,
                'funding_source' => $source,
                'raw_data' => [],
            ]);
        }

        $response = $this->actingAs($user)->get(route('investments.projects.index', [
            'universe' => 'governor',
            'funding_source' => 'SGR',
        ]));

        $response->assertOk()->assertSee('Proyecto con regalías')->assertDontSee('Proyecto con recursos propios');
    }

    public function test_map_endpoint_enriches_official_divipola_geometry_with_project_counts(): void
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
        $user = User::factory()->create();
        $project = InvestmentProject::factory()->create(['is_territory_meta' => true]);
        InvestmentLocation::create([
            'investment_project_id' => $project->id,
            'source_dataset_id' => 'xikz-44ja',
            'source_row_hash' => str_repeat('f', 64),
            'department_code' => '50',
            'municipality_code' => '50001',
            'municipality' => 'Villavicencio',
            'raw_data' => [],
        ]);

        $response = $this->actingAs($user)->getJson(route('investments.map', [
            'universe' => 'territory',
            'period_mode' => 'horizon',
        ]));

        $response->assertOk()
            ->assertJsonPath('features.0.properties.project_count', 1)
            ->assertJsonPath('features.0.properties.mpio_cdpmp', '50001');
    }
}
