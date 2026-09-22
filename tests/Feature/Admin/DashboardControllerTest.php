<?php

namespace Tests\Feature\Admin;

use App\Enums\DashboardStatus;
use App\Enums\UserRole;
use App\Models\Dashboard;
use App\Models\TabularDataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_siid_manager_creates_population_dashboard_and_edits_own_draft(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);

        $this->actingAs($manager)->post(route('admin.dashboards.store'), [
            'name' => 'Perfil poblacional', 'slug' => 'perfil-poblacional', 'template' => 'population',
        ])->assertRedirect();

        $dashboard = Dashboard::query()->where('slug', 'perfil-poblacional')->firstOrFail();
        $this->assertSame($manager->id, $dashboard->owner_id);
        $this->assertCount(9, $dashboard->draft_config['widgets']);
        $this->assertSame('departamental_fijo', $dashboard->draft_config['widgets'][0]['scope']);
        $this->assertSame('population_pyramid', $dashboard->draft_config['widgets'][7]['query']['operation']);
        $this->actingAs($manager)->get(route('admin.dashboards.edit', $dashboard))->assertOk()->assertSee('Constructor');
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
            'mpio', 'ano', 'area_geografica', 'hombres_0_anos', 'mujeres_0_anos',
            'hombres_12_anos', 'mujeres_12_anos', 'hombres_60_anos', 'mujeres_60_anos',
        ])->map(fn (string $key): array => ['key' => $key, 'label' => $key, 'type' => $key === 'mpio' ? 'text' : 'integer', 'visibility' => 'public'])->all();
        $source->versions()->create([
            'version' => 1, 'original_filename' => 'poblacion.xlsx', 'checksum' => str_repeat('b', 64), 'row_count' => 4,
            'fields' => $fields,
            'records' => [
                ['mpio' => '50001', 'ano' => 2026, 'area_geografica' => 'Total', 'hombres_0_anos' => 10, 'mujeres_0_anos' => 11, 'hombres_12_anos' => 20, 'mujeres_12_anos' => 21, 'hombres_60_anos' => 30, 'mujeres_60_anos' => 31],
                ['mpio' => '50001', 'ano' => 2026, 'area_geografica' => 'Cabecera Municipal', 'hombres_0_anos' => 999, 'mujeres_0_anos' => 999],
                ['mpio' => '50001', 'ano' => 2027, 'area_geografica' => 'Total', 'hombres_0_anos' => 999, 'mujeres_0_anos' => 999],
                ['mpio' => '50006', 'ano' => 2026, 'area_geografica' => 'Total', 'hombres_0_anos' => 999, 'mujeres_0_anos' => 999],
            ],
        ]);
        $config = ['data_source_id' => $source->id, 'map' => ['join_data_field' => 'mpio'], 'widgets' => [[
            'id' => 'pyramid', 'type' => 'pyramid', 'title' => 'Pirámide', 'scope' => 'seleccion_territorial', 'x' => 0, 'y' => 0, 'w' => 4, 'h' => 4,
            'query' => ['operation' => 'population_pyramid', 'year' => '2026', 'area' => 'Total'],
        ]]];
        $dashboard = Dashboard::factory()->create(['status' => DashboardStatus::Published, 'published_version' => 1, 'published_at' => now(), 'draft_config' => $config]);
        $dashboard->versions()->create(['version' => 1, 'config' => $config, 'published_at' => now()]);

        $this->getJson(route('dashboards.query', [$dashboard, 'widget' => 'pyramid', 'filters' => ['mpio' => '50001']]))
            ->assertOk()
            ->assertJsonPath('rows.0.label', '60-100+')
            ->assertJsonPath('rows.0.series.0.value', 31)
            ->assertJsonPath('rows.0.series.1.value', 30)
            ->assertJsonPath('rows.3.label', '12-18')
            ->assertJsonPath('rows.3.series.0.value', 21)
            ->assertJsonPath('rows.5.series.1.value', 10);
    }
}
