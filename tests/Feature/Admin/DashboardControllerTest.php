<?php

namespace Tests\Feature\Admin;

use App\Enums\DashboardEngine;
use App\Enums\DashboardStatus;
use App\Enums\UserRole;
use App\Models\Dashboard;
use App\Models\GeoViewer;
use App\Models\TabularDataSource;
use App\Models\User;
use App\Services\Superset\AprovisionadorDashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_dashboard_uses_superset_and_opens_the_guided_wizard(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);

        $this->actingAs($manager)->post(route('admin.dashboards.store'), [
            'name' => 'Ejecución presupuestal',
            'slug' => 'ejecucion-presupuestal',
            'description' => 'Tablero construido sobre Superset.',
            'template' => 'superset_basic',
        ])->assertRedirect();

        $dashboard = Dashboard::query()->where('slug', 'ejecucion-presupuestal')->firstOrFail();

        $this->assertSame(DashboardEngine::Superset, $dashboard->engine);
        $this->assertSame(['dataset_name' => null, 'charts' => []], $dashboard->wizard_config);

        $this->actingAs($manager)
            ->followingRedirects()
            ->get(route('admin.dashboards.edit', $dashboard))
            ->assertOk()
            ->assertSee('Constructor básico · Motor Superset')
            ->assertSee('Guardar y continuar')
            ->assertDontSee('Pirámide');
    }

    public function test_owner_saves_the_superset_wizard_without_using_the_legacy_json_config(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $dashboard = Dashboard::factory()->create([
            'owner_id' => $manager->id,
            'engine' => DashboardEngine::Superset,
            'wizard_config' => ['dataset_name' => null, 'charts' => []],
        ]);
        $legacyConfig = $dashboard->draft_config;

        $this->actingAs($manager)->patch(route('admin.dashboards.superset.update', $dashboard), [
            'name' => 'Pasiva por fuente',
            'description' => 'Detalle acumulado.',
            'dataset_name' => 'v_pasiva_fuente_sector',
            'charts' => [[
                'title' => 'Comprometido',
                'type' => 'bar',
                'dimension' => 'sector',
                'metric' => 'comprometido',
                'aggregation' => 'SUM',
            ]],
        ])->assertRedirect()->assertSessionHas('status');

        $dashboard->refresh();
        $this->assertSame('Pasiva por fuente', $dashboard->name);
        $this->assertSame('v_pasiva_fuente_sector', $dashboard->wizard_config['dataset_name']);
        $this->assertSame('Comprometido', $dashboard->wizard_config['charts'][0]['title']);
        $this->assertSame($legacyConfig, $dashboard->draft_config);
        $this->assertNotEmpty($dashboard->wizard_config['charts'][0]['key']);
    }

    public function test_owner_provisions_the_guided_dashboard_in_superset_and_enables_preview(): void
    {
        Cache::forget('superset.access_token');
        config([
            'superset.url' => 'http://superset.test',
            'superset.username' => 'servicio',
            'superset.password' => 'secreto',
            'superset.database_id' => 3,
            'superset.embed_allowed_domains' => ['http://siid.test'],
        ]);
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $dashboard = Dashboard::factory()->create([
            'owner_id' => $manager->id,
            'engine' => DashboardEngine::Superset,
            'wizard_config' => [
                'dataset_name' => 'v_pasiva_fuente_sector',
                'charts' => [[
                    'key' => '968f49c7-e15d-4abe-8c6a-0c1ba63fd8fa',
                    'title' => 'Comprometido por sector',
                    'type' => 'bar',
                    'dimension' => 'sector',
                    'metric' => 'comprometido',
                    'aggregation' => 'SUM',
                ]],
            ],
        ]);

        Http::fake(function (HttpRequest $request) {
            if (str_ends_with($request->url(), '/api/v1/security/login')) {
                return Http::response(['access_token' => 'token']);
            }
            if (str_ends_with($request->url(), '/api/v1/security/csrf_token/')) {
                return Http::response(['result' => 'csrf']);
            }
            if (str_contains($request->url(), '/api/v1/dataset/') && $request->method() === 'GET') {
                return Http::response(['result' => [['id' => 8, 'schema' => 'analitica', 'table_name' => 'v_pasiva_fuente_sector']]]);
            }
            if (str_ends_with($request->url(), '/api/v1/dashboard/') && $request->method() === 'POST') {
                return Http::response(['id' => 15], 201);
            }
            if (str_ends_with($request->url(), '/api/v1/chart/') && $request->method() === 'POST') {
                return Http::response(['id' => 27], 201);
            }
            if (str_ends_with($request->url(), '/api/v1/dashboard/15/embedded')) {
                return Http::response(['result' => ['uuid' => '4f69b6c1-7c67-4b94-bd9a-bf90e46b5adb']], 201);
            }

            return Http::response(['message' => 'ok']);
        });

        $response = $this->actingAs($manager)
            ->post(route('admin.dashboards.superset.sync', $dashboard));
        $response->assertRedirect();
        if ($response->getSession()->has('error')) {
            $this->fail((string) $response->getSession()->get('error'));
        }
        $response->assertSessionHas('status');

        $dashboard->refresh();
        $this->assertSame(15, $dashboard->superset_dashboard_id);
        $this->assertSame(8, $dashboard->superset_dataset_id);
        $this->assertSame(27, $dashboard->wizard_config['charts'][0]['superset_chart_id']);
        $this->assertSame('4f69b6c1-7c67-4b94-bd9a-bf90e46b5adb', $dashboard->superset_embedded_uuid);
        $this->assertNotNull($dashboard->superset_synced_at);

        Http::assertSent(fn (HttpRequest $request): bool => $request->method() === 'POST'
            && str_ends_with($request->url(), '/api/v1/chart/')
            && $request->data()['dashboards'] === [15]);
    }

    public function test_superset_dashboard_cannot_be_submitted_with_unsynchronized_changes(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $dashboard = Dashboard::factory()->create([
            'owner_id' => $manager->id,
            'engine' => DashboardEngine::Superset,
            'superset_synced_at' => null,
        ]);

        $this->actingAs($manager)
            ->post(route('admin.dashboards.submit', $dashboard))
            ->assertRedirect()
            ->assertSessionHas('error', 'Sincronice el tablero con Superset antes de enviarlo a revisión.');

        $this->assertSame(DashboardStatus::Draft, $dashboard->fresh()->status);
    }

    public function test_approved_superset_dashboard_keeps_a_remote_snapshot_as_its_public_version(): void
    {
        $author = User::factory()->create(['role' => UserRole::SiidManager]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dashboard = Dashboard::factory()->create([
            'owner_id' => $author->id,
            'engine' => DashboardEngine::Superset,
            'status' => DashboardStatus::PendingReview,
            'superset_dashboard_id' => 15,
            'superset_embedded_uuid' => '4f69b6c1-7c67-4b94-bd9a-bf90e46b5adb',
            'superset_synced_at' => now(),
            'wizard_config' => ['dataset_name' => 'v_pasiva_fuente_sector', 'charts' => []],
        ]);
        $this->mock(AprovisionadorDashboard::class)
            ->shouldReceive('publicar')
            ->once()
            ->withArgs(fn (Dashboard $received): bool => $received->is($dashboard));

        $this->actingAs($manager)
            ->post(route('admin.dashboards.publication.store', $dashboard))
            ->assertRedirect()
            ->assertSessionHas('status');

        $dashboard->refresh();
        $this->assertSame(DashboardStatus::Published, $dashboard->status);
        $this->assertSame('superset', $dashboard->versions()->firstOrFail()->config['engine']);
        $this->assertSame(15, $dashboard->versions()->firstOrFail()->config['superset_dashboard_id']);
    }

    public function test_published_superset_dashboard_uses_the_embedded_public_flow(): void
    {
        Cache::forget('superset.access_token');
        config([
            'superset.url' => 'http://superset.test',
            'superset.username' => 'servicio',
            'superset.password' => 'secreto',
        ]);
        $dashboard = Dashboard::factory()->create([
            'engine' => DashboardEngine::Superset,
            'status' => DashboardStatus::Published,
            'published_version' => 1,
            'superset_dashboard_id' => 15,
            'superset_embedded_uuid' => '4f69b6c1-7c67-4b94-bd9a-bf90e46b5adb',
        ]);
        Http::fake(function (HttpRequest $request) {
            if (str_ends_with($request->url(), '/api/v1/security/login')) {
                return Http::response(['access_token' => 'acceso']);
            }
            if (str_ends_with($request->url(), '/api/v1/security/csrf_token/')) {
                return Http::response(['result' => 'csrf']);
            }

            return Http::response(['token' => 'invitado']);
        });

        $this->get(route('dashboards.embed', $dashboard))
            ->assertOk()
            ->assertSee('data-superset', false)
            ->assertSee(route('dashboards.superset.public-token', $dashboard), false);
        $this->getJson(route('dashboards.superset.public-token', $dashboard))
            ->assertOk()
            ->assertJsonPath('token', 'invitado')
            ->assertJsonPath('uuid', '4f69b6c1-7c67-4b94-bd9a-bf90e46b5adb');
    }

    public function test_siid_manager_creates_population_dashboard_and_edits_own_draft(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);

        $this->actingAs($manager)->post(route('admin.dashboards.store'), [
            'name' => 'Perfil poblacional', 'slug' => 'perfil-poblacional', 'template' => 'population',
        ])->assertRedirect();

        $dashboard = Dashboard::query()->where('slug', 'perfil-poblacional')->firstOrFail();
        $this->assertSame($manager->id, $dashboard->owner_id);
        $this->assertCount(9, $dashboard->draft_config['widgets']);
        $this->assertSame(2026, $dashboard->draft_config['population_year']);
        $this->assertSame('departamental_fijo', $dashboard->draft_config['widgets'][0]['scope']);
        $this->assertSame('population_pyramid', $dashboard->draft_config['widgets'][7]['query']['operation']);
        $this->actingAs($manager)->followingRedirects()->get(route('admin.dashboards.edit', $dashboard))->assertOk()->assertSee('Constructor');
    }

    public function test_only_manager_or_admin_approves_dashboard_and_public_version_is_immutable(): void
    {
        $author = User::factory()->create(['role' => UserRole::SiidManager]);
        $reviewer = User::factory()->create(['role' => UserRole::Reviewer]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dashboard = Dashboard::factory()->create(['owner_id' => $author->id, 'status' => DashboardStatus::PendingReview]);

        $this->actingAs($reviewer)->post(route('admin.dashboards.publication.store', $dashboard))->assertForbidden();
        $this->actingAs($manager)->post(route('admin.dashboards.publication.store', $dashboard))->assertRedirect();

        $dashboard->refresh();
        $this->assertSame(1, $dashboard->published_version);
        $this->assertDatabaseHas('dashboard_versions', ['dashboard_id' => $dashboard->id, 'version' => 1, 'approved_by' => $manager->id]);
        $publishedConfig = $dashboard->versions()->firstOrFail()->config;

        $changed = $dashboard->draft_config;
        $changed['widgets'][0]['title'] = 'Título nuevo';
        $this->actingAs($author)->patchJson(route('admin.dashboards.update', $dashboard), ['config' => $changed])->assertOk();

        $this->assertSame($publishedConfig, $dashboard->versions()->firstOrFail()->config);
        $this->get(route('dashboards.config', $dashboard))->assertOk()->assertJsonPath('config.widgets.0.title', $publishedConfig['widgets'][0]['title']);
    }

    public function test_no_op_autosave_keeps_a_published_dashboard_pending_review(): void
    {
        $author = User::factory()->create(['role' => UserRole::SiidManager]);
        $submittedAt = now()->subMinute();
        $dashboard = Dashboard::factory()->create([
            'owner_id' => $author->id,
            'status' => DashboardStatus::PendingReview,
            'published_version' => 1,
            'published_at' => now()->subDay(),
            'submitted_at' => $submittedAt,
        ]);
        $originalConfig = $dashboard->draft_config;

        $this->actingAs($author)
            ->patchJson(route('admin.dashboards.update', $dashboard), ['config' => $dashboard->draft_config])
            ->assertOk();

        $dashboard->refresh();
        $this->assertEquals($originalConfig, $dashboard->draft_config);
        $this->assertSame(DashboardStatus::PendingReview, $dashboard->status);
        $this->assertSame($submittedAt->toDateTimeString(), $dashboard->submitted_at->toDateTimeString());
    }

    public function test_publication_outside_pending_review_returns_an_actionable_message(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $dashboard = Dashboard::factory()->create(['status' => DashboardStatus::Draft]);

        $this->actingAs($manager)
            ->from(route('admin.dashboards.edit', $dashboard))
            ->post(route('admin.dashboards.publication.store', $dashboard))
            ->assertRedirect(route('admin.dashboards.edit', $dashboard))
            ->assertSessionHas('error', 'El dashboard cambió después de enviarse a revisión. Envíelo nuevamente a revisión antes de publicarlo.');
    }

    public function test_builder_lists_only_published_geo_viewers(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $dashboard = Dashboard::factory()->create(['owner_id' => $manager->id]);
        $published = GeoViewer::factory()->published()->create(['name' => 'Visor municipal publicado']);
        GeoViewer::factory()->create(['name' => 'Visor municipal en borrador']);

        $this->actingAs($manager)
            ->followingRedirects()
            ->get(route('admin.dashboards.edit', $dashboard))
            ->assertOk()
            ->assertSee($published->name)
            ->assertDontSee('Visor municipal en borrador');
    }

    public function test_builder_reports_complete_population_years_from_the_current_source_version(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $dashboard = Dashboard::factory()->create(['owner_id' => $manager->id]);
        $source = TabularDataSource::factory()->create(['current_version' => 2]);
        $source->versions()->create([
            'version' => 2,
            'original_filename' => 'meta.xlsx',
            'checksum' => str_repeat('c', 64),
            'row_count' => 4,
            'fields' => [],
            'records' => [
                ['ano' => 2026, 'area_geografica' => 'Cabecera Municipal'],
                ['ano' => 2026, 'area_geografica' => 'Centros Poblados y Rural Disperso'],
                ['ano' => 2027, 'area_geografica' => 'Total'],
                ['ano' => 2027, 'area_geografica' => 'Total'],
            ],
        ]);

        $this->actingAs($manager)->followingRedirects()->get(route('admin.dashboards.edit', $dashboard))
            ->assertOk()
            ->assertSee('2027')
            ->assertSee('2026');
    }

    public function test_dashboard_with_map_cannot_be_submitted_until_geo_viewer_is_published(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $viewer = GeoViewer::factory()->create(['name' => 'Visor municipal en borrador']);
        $dashboard = Dashboard::factory()->create([
            'owner_id' => $manager->id,
            'draft_config' => [
                'data_source_id' => null,
                'map' => [
                    'geo_viewer_id' => $viewer->id,
                    'join_layer_field' => 'mp_codigo',
                    'join_data_field' => 'mpio',
                ],
                'global_filters' => [],
                'widgets' => [[
                    'id' => 'mapa', 'type' => 'map', 'title' => 'Municipios', 'scope' => 'global',
                    'x' => 0, 'y' => 0, 'w' => 12, 'h' => 6, 'query' => [],
                ]],
            ],
        ]);

        $this->actingAs($manager)
            ->post(route('admin.dashboards.submit', $dashboard))
            ->assertRedirect()
            ->assertSessionHas('error', 'El geovisor seleccionado todavía no está publicado. Publíquelo antes de enviar el dashboard a revisión.');

        $this->assertSame(DashboardStatus::Draft, $dashboard->fresh()->status);
    }

    public function test_territorial_query_filters_dynamic_widget_but_not_fixed_widget(): void
    {
        $source = TabularDataSource::factory()->create(['current_version' => 1]);
        $source->versions()->create([
            'version' => 1, 'original_filename' => 'poblacion.csv', 'checksum' => str_repeat('a', 64), 'row_count' => 2,
            'fields' => [
                ['key' => 'codigo_dane', 'label' => 'Código', 'type' => 'text', 'visibility' => 'public'],
                ['key' => 'poblacion_total', 'label' => 'Total', 'type' => 'integer', 'visibility' => 'public'],
                ['key' => 'secreto', 'label' => 'Secreto', 'type' => 'text', 'visibility' => 'internal'],
            ],
            'records' => [['codigo_dane' => '50001', 'poblacion_total' => 10, 'secreto' => 'x'], ['codigo_dane' => '50002', 'poblacion_total' => 20, 'secreto' => 'y']],
        ]);
        $config = ['data_source_id' => $source->id, 'map' => ['join_data_field' => 'codigo_dane'], 'widgets' => [
            ['id' => 'fixed', 'type' => 'indicator', 'title' => 'Departamento', 'scope' => 'departamental_fijo', 'x' => 0, 'y' => 0, 'w' => 2, 'h' => 2, 'query' => ['operation' => 'sum', 'field' => 'poblacion_total']],
            ['id' => 'local', 'type' => 'indicator', 'title' => 'Municipio', 'scope' => 'seleccion_territorial', 'x' => 2, 'y' => 0, 'w' => 2, 'h' => 2, 'query' => ['operation' => 'sum', 'field' => 'poblacion_total']],
            ['id' => 'table', 'type' => 'table', 'title' => 'Detalle', 'scope' => 'global', 'x' => 4, 'y' => 0, 'w' => 4, 'h' => 2, 'query' => []],
        ]];
        $dashboard = Dashboard::factory()->create(['status' => DashboardStatus::Published, 'published_version' => 1, 'published_at' => now(), 'draft_config' => $config]);
        $dashboard->versions()->create(['version' => 1, 'config' => $config, 'published_at' => now()]);

        $this->getJson(route('dashboards.query', [$dashboard, 'widget' => 'fixed', 'filters' => ['codigo_dane' => '50001']]))->assertOk()->assertJsonPath('value', 30);
        $this->getJson(route('dashboards.query', [$dashboard, 'widget' => 'local', 'filters' => ['codigo_dane' => '50001']]))->assertOk()->assertJsonPath('value', 10);
        $this->getJson(route('dashboards.query', [$dashboard, 'widget' => 'table']))->assertOk()->assertJsonMissing(['secreto'])->assertJsonMissing(['x']);
    }

    public function test_population_pyramid_understands_wide_age_and_sex_columns(): void
    {
        $source = TabularDataSource::factory()->create(['current_version' => 1]);
        $fields = collect([
            'mpio', 'ano', 'area_geografica', 'total', 'hombres', 'mujeres', 'hombres_0_anos', 'mujeres_0_anos',
            'hombres_12_anos', 'mujeres_12_anos', 'hombres_60_anos', 'mujeres_60_anos',
        ])->map(fn (string $key): array => ['key' => $key, 'label' => $key, 'type' => $key === 'mpio' ? 'text' : 'integer', 'visibility' => 'public'])->all();
        $source->versions()->create([
            'version' => 1, 'original_filename' => 'poblacion.xlsx', 'checksum' => str_repeat('b', 64), 'row_count' => 5,
            'fields' => $fields,
            'records' => [
                ['mpio' => '50001', 'ano' => 2026, 'area_geografica' => 'Total', 'total' => 61, 'hombres' => 29, 'mujeres' => 32, 'hombres_0_anos' => 10, 'mujeres_0_anos' => 11, 'hombres_12_anos' => 20, 'mujeres_12_anos' => 21, 'hombres_60_anos' => 30, 'mujeres_60_anos' => 31],
                ['mpio' => '50001', 'ano' => 2026, 'area_geografica' => 'Cabecera Municipal', 'total' => 40, 'hombres_0_anos' => 999, 'mujeres_0_anos' => 999],
                ['mpio' => '50001', 'ano' => 2026, 'area_geografica' => 'Centros Poblados y Rural Disperso', 'total' => 21],
                ['mpio' => '50001', 'ano' => 2027, 'area_geografica' => 'Total', 'total' => 999, 'hombres_0_anos' => 999, 'mujeres_0_anos' => 999],
                ['mpio' => '50006', 'ano' => 2026, 'area_geografica' => 'Total', 'total' => 999, 'hombres_0_anos' => 999, 'mujeres_0_anos' => 999],
            ],
        ]);
        $config = ['data_source_id' => $source->id, 'map' => ['join_data_field' => 'mpio'], 'widgets' => [
            ['id' => 'pyramid', 'type' => 'pyramid', 'title' => 'Pirámide', 'scope' => 'seleccion_territorial', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 4, 'query' => ['operation' => 'population_pyramid', 'year' => '2026', 'area' => 'Total']],
            ['id' => 'total', 'type' => 'indicator', 'title' => 'Total', 'scope' => 'seleccion_territorial', 'x' => 4, 'y' => 0, 'w' => 2, 'h' => 2, 'query' => ['operation' => 'population_indicator', 'field' => 'total', 'year' => '2026', 'area' => 'Total']],
            ['id' => 'area', 'type' => 'donut', 'title' => 'Área', 'scope' => 'seleccion_territorial', 'x' => 6, 'y' => 0, 'w' => 2, 'h' => 2, 'query' => ['operation' => 'population_area_distribution', 'field' => 'total', 'year' => '2026']],
        ]];
        $dashboard = Dashboard::factory()->create(['status' => DashboardStatus::Published, 'published_version' => 1, 'published_at' => now(), 'draft_config' => $config]);
        $dashboard->versions()->create(['version' => 1, 'config' => $config, 'published_at' => now()]);

        $this->getJson(route('dashboards.query', [$dashboard, 'widget' => 'pyramid', 'filters' => ['mpio' => '50001']]))
            ->assertOk()
            ->assertJsonPath('rows.0.label', '60-100+')
            ->assertJsonPath('rows.0.value', 61)
            ->assertJsonPath('rows.0.series.0.value', 31)
            ->assertJsonPath('rows.0.series.1.value', 30)
            ->assertJsonPath('rows.3.label', '12-18')
            ->assertJsonPath('rows.3.value', 41)
            ->assertJsonPath('rows.3.series.0.value', 21)
            ->assertJsonPath('rows.5.value', 21)
            ->assertJsonPath('rows.5.series.1.value', 10);
        $this->getJson(route('dashboards.query', [$dashboard, 'widget' => 'total', 'filters' => ['mpio' => '50001']]))
            ->assertOk()->assertJsonPath('value', 61);
        $this->getJson(route('dashboards.query', [$dashboard, 'widget' => 'area', 'filters' => ['mpio' => '50001']]))
            ->assertOk()->assertJsonCount(2, 'rows')->assertJsonFragment(['label' => 'Cabecera Municipal', 'value' => 40])->assertJsonFragment(['label' => 'Centros Poblados y Rural Disperso', 'value' => 21]);
    }
}
