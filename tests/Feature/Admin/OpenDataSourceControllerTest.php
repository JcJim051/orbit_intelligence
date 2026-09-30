<?php

namespace Tests\Feature\Admin;

use App\Enums\GeoViewerStatus;
use App\Enums\UserRole;
use App\Models\OpenDataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenDataSourceControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/views/meta-1234')) {
                return Http::response([
                    'name' => 'Inventario nacional',
                    'columns' => [
                        ['fieldName' => 'departamento', 'name' => 'Departamento', 'dataTypeName' => 'text'],
                        ['fieldName' => 'latitud', 'name' => 'Latitud', 'dataTypeName' => 'number'],
                        ['fieldName' => 'longitud', 'name' => 'Longitud', 'dataTypeName' => 'number'],
                        ['fieldName' => 'nombre', 'name' => 'Nombre', 'dataTypeName' => 'text'],
                    ],
                ]);
            }
            if (str_contains($request->url(), '/resource/meta-1234.json')) {
                if (($request->data()['$select'] ?? null) === 'count(*) as total') {
                    return Http::response([['total' => '2']]);
                }
                if (($request->data()['$group'] ?? null) === 'departamento') {
                    return Http::response([['departamento' => 'CUNDINAMARCA'], ['departamento' => 'META']]);
                }
                if (str_contains((string) ($request->data()['$where'] ?? ''), "departamento = 'META'")) {
                    return Http::response([['departamento' => 'META', 'latitud' => '4.15', 'longitud' => '-73.63', 'nombre' => 'Sede Meta']]);
                }

                return Http::response([
                    ['departamento' => 'CUNDINAMARCA', 'latitud' => '4.70', 'longitud' => '-74.10', 'nombre' => 'Sede externa'],
                    ['departamento' => 'META', 'latitud' => '4.15', 'longitud' => '-73.63', 'nombre' => 'Sede Meta'],
                ]);
            }
            if (str_contains($request->url(), '/api/views/abcd-1234')) {
                return Http::response([
                    'name' => 'Puntos institucionales',
                    'description' => 'Inventario público',
                    'attribution' => 'Entidad de prueba',
                    'rowsUpdatedAt' => 1_795_000_000,
                    'columns' => [
                        ['fieldName' => 'latitud', 'name' => 'Latitud', 'dataTypeName' => 'number'],
                        ['fieldName' => 'longitud', 'name' => 'Longitud', 'dataTypeName' => 'number'],
                        ['fieldName' => 'nombre', 'name' => 'Nombre', 'dataTypeName' => 'text'],
                        ['fieldName' => 'anio', 'name' => 'Año', 'dataTypeName' => 'number'],
                        ['fieldName' => 'valor', 'name' => 'Valor', 'dataTypeName' => 'number'],
                        ['fieldName' => 'interno', 'name' => 'Interno', 'dataTypeName' => 'text'],
                    ],
                ]);
            }

            if (str_contains($request->url(), '/resource/abcd-1234.json')) {
                if (($request->data()['$select'] ?? null) === 'count(*) as total') {
                    return Http::response([['total' => '2']]);
                }

                return Http::response([
                    ['latitud' => '4.15', 'longitud' => '-73.63', 'nombre' => 'Sede A', 'anio' => '2026', 'valor' => '10', 'interno' => 'secreto'],
                    ['latitud' => '3.98', 'longitud' => '-73.75', 'nombre' => 'Sede B', 'anio' => '2027', 'valor' => '20', 'interno' => 'reservado'],
                ]);
            }

            return Http::response([], 404);
        });
    }

    public function test_siid_manager_analyzes_and_creates_an_owned_draft_with_public_field_allowlist(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);

        $this->actingAs($manager)->postJson(route('admin.open-data-sources.analyze'), [
            'url' => 'https://www.datos.gov.co/d/abcd-1234',
        ])->assertOk()->assertJsonPath('geography.mode', 'coordinates');

        $response = $this->actingAs($manager)->postJson(route('admin.open-data-sources.store'), $this->payload());
        $response->assertCreated()->assertJsonPath('source.slug', 'puntos-institucionales');

        $source = OpenDataSource::firstOrFail();
        $this->assertSame($manager->id, $source->owner_id);
        $this->assertSame(['nombre', 'valor'], $source->popup_fields);
        $this->assertNotNull($source->last_success_at);
        $this->assertDatabaseHas('geo_viewers', ['slug' => 'puntos-institucionales-visor', 'owner_id' => $manager->id, 'status' => 'draft']);
        $this->assertDatabaseHas('open_data_snapshots', ['open_data_source_id' => $source->id, 'feature_count' => 2]);
    }

    public function test_public_endpoint_never_exposes_unselected_fields_and_rejects_unknown_filters(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->postJson(route('admin.open-data-sources.store'), $this->payload())->assertCreated();
        $source = OpenDataSource::firstOrFail();
        $viewer = $source->layer->viewers()->firstOrFail();
        $viewer->update(['status' => GeoViewerStatus::Published, 'published_at' => now()]);

        $this->getJson(route('geodata.open-data-source', $source).'?anio=2026&revision=123')
            ->assertOk()
            ->assertJsonPath('features.0.properties.nombre', 'Sede A')
            ->assertJsonMissing(['interno' => 'secreto']);

        $this->getJson(route('geodata.open-data-source', $source).'?sql=drop')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Se recibió un filtro no autorizado.');
    }

    public function test_manager_cannot_create_but_can_publish_a_submitted_viewer(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager)->postJson(route('admin.open-data-sources.store'), $this->payload())->assertForbidden();
    }

    public function test_manager_can_lock_a_national_source_to_meta_from_the_import_wizard(): void
    {
        $manager = User::factory()->create(['role' => UserRole::SiidManager]);
        $analysis = $this->actingAs($manager)->postJson(route('admin.open-data-sources.analyze'), [
            'url' => 'https://www.datos.gov.co/d/meta-1234',
        ])->assertOk()
            ->assertJsonPath('territorial_filter.available', true)
            ->assertJsonPath('territorial_filter.field', 'departamento')
            ->assertJsonPath('territorial_filter.value', 'META');

        $this->assertCount(1, $analysis->json('preview.features'));

        $payload = $this->payload();
        $payload['url'] = 'https://www.datos.gov.co/d/meta-1234';
        $payload['slug'] = 'inventario-meta';
        $payload['viewer_slug'] = 'inventario-meta-visor';
        $payload['metric_field'] = null;
        $payload['filters'] = [];
        $payload['popup_fields'] = ['nombre'];
        $payload['limit_to_meta'] = true;
        $payload['department_field'] = 'departamento';
        $payload['department_value'] = 'META';

        $this->actingAs($manager)->postJson(route('admin.open-data-sources.store'), $payload)->assertCreated();

        $source = OpenDataSource::query()->where('slug', 'inventario-meta')->firstOrFail();
        $this->assertSame('departamento', $source->scope_filters[0]['field']);
        $this->assertSame('META', $source->scope_filters[0]['value']);
        $this->assertDatabaseHas('open_data_snapshots', ['open_data_source_id' => $source->id, 'feature_count' => 1]);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/resource/meta-1234.json')
            && str_contains((string) ($request->data()['$where'] ?? ''), "departamento = 'META'"));
    }

    private function payload(): array
    {
        return [
            'url' => 'https://www.datos.gov.co/d/abcd-1234',
            'name' => 'Puntos institucionales',
            'slug' => 'puntos-institucionales',
            'description' => 'Inventario público',
            'attribution' => 'Entidad de prueba · Datos.gov.co',
            'label_field' => 'nombre',
            'metric_field' => 'valor',
            'aggregation' => 'sum',
            'popup_fields' => ['nombre', 'valor'],
            'filters' => ['anio'],
            'palette' => 'green',
            'no_data_color' => '#d1d5db',
            'opacity' => .75,
            'target' => 'new',
            'viewer_name' => 'Puntos institucionales',
            'viewer_slug' => 'puntos-institucionales-visor',
        ];
    }
}
