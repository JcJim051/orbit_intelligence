<?php

namespace Tests\Feature\Admin;

use App\Enums\GeoLayerAccessPolicy;
use App\Enums\GeoViewerStatus;
use App\Enums\UserRole;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoViewerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_access_geo_publication_administration(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)
            ->get(route('admin.geo-viewers.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_viewers_and_layer_assignments_in_publication_panel(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = GeoViewer::factory()->create(['name' => 'Visor ambiental']);
        $layer = GeoLayer::factory()->create(['name' => 'Fuentes hídricas']);
        $viewer->layers()->attach($layer, [
            'group_name' => 'Hídrico ambiental',
            'sort_order' => 10,
            'visible_by_default' => true,
            'show_in_legend' => true,
            'opacity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.geo-viewers.index'))
            ->assertOk()
            ->assertSee('Visor ambiental')
            ->assertSee('Fuentes hídricas')
            ->assertSee('Hídrico ambiental');
    }

    public function test_admin_creates_draft_viewer_and_audit_record(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-viewers.store'), [
            'name' => 'Gestión del riesgo',
            'slug' => 'gestion-del-riesgo',
            'description' => 'Puntos críticos del departamento.',
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => 'draft',
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('geo_viewers', [
            'slug' => 'gestion-del-riesgo',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'geo_viewer_created',
            'stage' => 'geovisors',
        ]);
    }

    public function test_new_layer_form_defaults_to_public_view_and_download(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.geo-viewers.index'))
            ->assertSee('value="downloadable" selected', false);
    }

    public function test_admin_cannot_create_viewer_as_published(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-viewers.store'), [
            'name' => 'Visor sin aprobación',
            'slug' => 'visor-sin-aprobacion',
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => 'published',
        ])->assertRedirect()->assertInvalid('status');

        $this->assertDatabaseMissing('geo_viewers', ['slug' => 'visor-sin-aprobacion']);
    }

    public function test_admin_registers_geojson_layer_with_safe_structured_style(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-layers.store'), [
            'name' => 'Puntos críticos',
            'slug' => 'puntos-criticos',
            'group_name' => 'Gestión del riesgo',
            'source_type' => 'geojson',
            'source_url' => 'https://datos.example.gov.co/puntos.geojson',
            'geometry_type' => 'point',
            'popup_fields' => "codigo, municipio\nnivel_riesgo",
            'color' => '#991b1b',
            'fill_color' => '#ef4444',
            'weight' => 2,
            'radius' => 8,
            'attribution' => 'Fuente: Gestión del Riesgo',
            'min_zoom' => 6,
            'max_zoom' => 18,
            'active' => 1,
        ])->assertRedirect()->assertSessionHas('status');

        $layer = GeoLayer::query()->where('slug', 'puntos-criticos')->firstOrFail();
        $this->assertSame(['codigo', 'municipio', 'nivel_riesgo'], $layer->popup_fields);
        $this->assertSame('#991b1b', $layer->style['color']);
        $this->assertTrue($layer->active);
        $this->assertSame(GeoLayerAccessPolicy::Downloadable, $layer->access_policy);
        $this->assertSame('geojson', $layer->download_format);
    }

    public function test_admin_registers_a_geoserver_wms_layer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-layers.store'), [
            'name' => 'Fuentes hídricas',
            'slug' => 'fuentes-hidricas',
            'group_name' => 'Hídrico ambiental',
            'source_type' => 'wms',
            'source_url' => 'https://geo.example.gov.co/geoserver/meta/wms',
            'source_layer_name' => 'meta:fuentes_hidricas',
            'geometry_type' => 'line',
            'color' => '#1d4ed8',
            'fill_color' => '#60a5fa',
            'weight' => 2,
            'radius' => 7,
            'min_zoom' => 0,
            'max_zoom' => 18,
            'active' => 1,
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('geo_layers', [
            'slug' => 'fuentes-hidricas',
            'source_type' => 'wms',
            'source_layer_name' => 'meta:fuentes_hidricas',
            'access_policy' => 'pending',
        ]);
    }

    public function test_wms_layer_requires_a_remote_layer_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-layers.store'), [
            'name' => 'Fuentes hídricas',
            'slug' => 'fuentes-hidricas',
            'source_type' => 'wms',
            'source_url' => 'https://geo.example.gov.co/geoserver/meta/wms',
            'geometry_type' => 'line',
            'color' => '#1d4ed8',
            'fill_color' => '#60a5fa',
            'weight' => 2,
            'radius' => 7,
            'min_zoom' => 0,
            'max_zoom' => 18,
        ])->assertRedirect()->assertInvalid('source_layer_name');
    }

    public function test_admin_can_register_a_same_origin_demonstrative_layer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-layers.store'), [
            'name' => 'Capa local',
            'slug' => 'capa-local',
            'source_type' => 'geojson',
            'source_url' => '/data/geovisores/capa-local.geojson',
            'geometry_type' => 'point',
            'color' => '#1d4ed8',
            'fill_color' => '#60a5fa',
            'weight' => 2,
            'radius' => 7,
            'min_zoom' => 0,
            'max_zoom' => 18,
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('geo_layers', [
            'slug' => 'capa-local',
            'source_url' => '/data/geovisores/capa-local.geojson',
        ]);
    }

    public function test_admin_can_register_a_same_origin_public_geodata_layer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-layers.store'), [
            'name' => 'Importación SIID',
            'slug' => 'importacion-siid',
            'source_type' => 'geojson',
            'source_url' => '/api/public/geodata/importacion-siid',
            'geometry_type' => 'polygon',
            'color' => '#1d4ed8',
            'fill_color' => '#60a5fa',
            'weight' => 2,
            'radius' => 7,
            'min_zoom' => 0,
            'max_zoom' => 18,
            'access_policy' => 'downloadable',
            'download_format' => 'geojson',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('geo_layers', [
            'slug' => 'importacion-siid',
            'source_url' => '/api/public/geodata/importacion-siid',
        ]);
    }

    public function test_admin_prepares_draft_viewer_with_selected_layer_configuration(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = GeoViewer::factory()->create();
        $layer = GeoLayer::factory()->create();

        $this->actingAs($admin)->patch(route('admin.geo-viewers.update', $viewer), [
            'name' => $viewer->name,
            'slug' => $viewer->slug,
            'description' => $viewer->description,
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 9,
            'status' => 'draft',
            'layers' => [[
                'geo_layer_id' => $layer->id,
                'included' => 1,
                'label' => 'Puntos priorizados',
                'group_name' => 'Riesgos',
                'sort_order' => 20,
                'visible_by_default' => 1,
                'show_in_legend' => 1,
                'opacity' => 0.75,
            ]],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('geo_viewers', [
            'id' => $viewer->id,
            'status' => GeoViewerStatus::Draft->value,
            'initial_zoom' => 9,
        ]);
        $this->assertDatabaseHas('geo_viewer_layers', [
            'geo_viewer_id' => $viewer->id,
            'geo_layer_id' => $layer->id,
            'label' => 'Puntos priorizados',
            'group_name' => 'Riesgos',
            'visible_by_default' => true,
        ]);
        $this->assertNull($viewer->fresh()->published_at);
    }

    public function test_admin_adds_classified_layer_to_published_viewer_without_changing_public_address(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = GeoViewer::factory()->published()->create();
        $existing = GeoLayer::factory()->create();
        $new = GeoLayer::factory()->create();
        $viewer->layers()->attach($existing, ['sort_order' => 10, 'visible_by_default' => true, 'show_in_legend' => true, 'opacity' => 1]);

        $this->actingAs($admin)->patch(route('admin.geo-viewers.update', $viewer), [
            'name' => $viewer->name,
            'slug' => $viewer->slug,
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => 'published',
            'layers' => [
                $this->layerAssignment($existing, 10),
                $this->layerAssignment($new, 20),
            ],
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertDatabaseHas('geo_viewers', ['id' => $viewer->id, 'slug' => $viewer->slug, 'status' => 'published', 'approved_by' => $admin->id]);
        $this->assertDatabaseHas('geo_viewer_layers', ['geo_viewer_id' => $viewer->id, 'geo_layer_id' => $existing->id]);
        $this->assertDatabaseHas('geo_viewer_layers', ['geo_viewer_id' => $viewer->id, 'geo_layer_id' => $new->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'geo_viewer_updated', 'actor_id' => $admin->id]);
    }

    public function test_admin_sees_edit_form_for_published_viewer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        GeoViewer::factory()->published()->create(['name' => 'Visor publicado']);

        $this->actingAs($admin)->get(route('admin.geo-viewers.index'))
            ->assertOk()
            ->assertSee('Visor publicado')
            ->assertSee('Guardar y aprobar cambios públicos');
    }

    public function test_admin_cannot_add_unclassified_layer_to_published_viewer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = GeoViewer::factory()->published()->create();
        $existing = GeoLayer::factory()->create();
        $new = GeoLayer::factory()->create(['access_policy' => GeoLayerAccessPolicy::Pending]);
        $viewer->layers()->attach($existing, ['sort_order' => 10, 'visible_by_default' => true, 'show_in_legend' => true, 'opacity' => 1]);

        $this->actingAs($admin)->patch(route('admin.geo-viewers.update', $viewer), [
            'name' => $viewer->name,
            'slug' => $viewer->slug,
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => 'published',
            'layers' => [$this->layerAssignment($existing, 10), $this->layerAssignment($new, 20)],
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('geo_viewer_layers', ['geo_viewer_id' => $viewer->id, 'geo_layer_id' => $new->id]);
    }

    public function test_published_viewer_address_cannot_change_during_edit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $viewer = GeoViewer::factory()->published()->create();

        $this->actingAs($admin)->patch(route('admin.geo-viewers.update', $viewer), [
            'name' => $viewer->name,
            'slug' => 'otra-direccion',
            'center_latitude' => 4.15,
            'center_longitude' => -73.63,
            'initial_zoom' => 8,
            'status' => 'published',
        ])->assertRedirect()->assertInvalid('slug');

        $this->assertSame($viewer->slug, $viewer->fresh()->slug);
    }

    /** @return array<string, int|string> */
    private function layerAssignment(GeoLayer $layer, int $order): array
    {
        return [
            'geo_layer_id' => $layer->id,
            'included' => 1,
            'sort_order' => $order,
            'visible_by_default' => 1,
            'show_in_legend' => 1,
            'opacity' => 1,
        ];
    }

    public function test_layer_rejects_non_http_source_url(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-layers.store'), [
            'name' => 'Capa insegura',
            'slug' => 'capa-insegura',
            'source_type' => 'geojson',
            'source_url' => 'javascript:alert(1)',
            'geometry_type' => 'point',
            'color' => '#991b1b',
            'fill_color' => '#ef4444',
            'weight' => 2,
            'radius' => 8,
            'min_zoom' => 0,
            'max_zoom' => 18,
        ])->assertRedirect()->assertInvalid('source_url');

        $this->assertDatabaseMissing('geo_layers', ['slug' => 'capa-insegura']);
    }

    public function test_geojson_layer_cannot_be_marked_as_view_only(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('admin.geo-layers.store'), [
            'name' => 'Capa supuestamente restringida',
            'slug' => 'capa-restringida',
            'source_type' => 'geojson',
            'source_url' => '/data/geovisores/capa-restringida.geojson',
            'geometry_type' => 'point',
            'access_policy' => 'view_only',
            'restriction_reason' => 'Contiene información de consulta.',
            'color' => '#991b1b',
            'fill_color' => '#ef4444',
            'weight' => 2,
            'radius' => 8,
            'min_zoom' => 0,
            'max_zoom' => 18,
        ])->assertRedirect()->assertInvalid('access_policy');

        $this->assertDatabaseMissing('geo_layers', ['slug' => 'capa-restringida']);
    }
}
