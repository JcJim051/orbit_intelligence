<?php

namespace Tests\Feature;

use App\Enums\GeoLayerAccessPolicy;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoViewerPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_viewer_is_not_publicly_accessible(): void
    {
        $viewer = GeoViewer::factory()->create(['slug' => 'visor-borrador']);

        $this->get(route('geo-viewers.embed', $viewer))->assertNotFound();
        $this->getJson(route('geo-viewers.config', $viewer))->assertNotFound();
    }

    public function test_published_viewer_returns_only_active_layers_in_configured_order(): void
    {
        $viewer = GeoViewer::factory()->published()->create([
            'name' => 'Geovisor territorial',
            'slug' => 'territorial',
        ]);
        $first = GeoLayer::factory()->create(['name' => 'Cuencas', 'slug' => 'cuencas']);
        $second = GeoLayer::factory()->create(['name' => 'Drenajes', 'slug' => 'drenajes']);
        $inactive = GeoLayer::factory()->create(['slug' => 'interno', 'active' => false]);
        $viewer->layers()->attach($second, [
            'group_name' => 'Hídrico ambiental', 'sort_order' => 20, 'visible_by_default' => false, 'show_in_legend' => true, 'opacity' => 0.6,
        ]);
        $viewer->layers()->attach($first, [
            'label' => 'Cuencas hidrográficas', 'group_name' => 'Hídrico ambiental', 'sort_order' => 10, 'visible_by_default' => true, 'show_in_legend' => true, 'opacity' => 0.8,
        ]);
        $viewer->layers()->attach($inactive, ['sort_order' => 1]);

        $response = $this->getJson(route('geo-viewers.config', $viewer));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(2, 'layers')
            ->assertJsonPath('viewer.name', 'Geovisor territorial')
            ->assertJsonPath('layers.0.slug', 'cuencas')
            ->assertJsonPath('layers.0.name', 'Cuencas hidrográficas')
            ->assertJsonPath('layers.0.visible_by_default', true)
            ->assertJsonPath('layers.0.download.allowed', true)
            ->assertJsonPath('layers.0.download.format', 'geojson')
            ->assertJsonPath('layers.1.slug', 'drenajes')
            ->assertJsonMissing(['slug' => 'interno']);
    }

    public function test_view_only_layer_has_no_download_link_in_public_configuration(): void
    {
        $viewer = GeoViewer::factory()->published()->create();
        $layer = GeoLayer::factory()->create([
            'source_type' => 'wms',
            'source_url' => 'https://geo.example.gov.co/geoserver/meta/wms',
            'source_layer_name' => 'meta:reservas',
            'access_policy' => GeoLayerAccessPolicy::ViewOnly,
            'download_format' => null,
            'restriction_reason' => 'Información de consulta restringida.',
        ]);
        $viewer->layers()->attach($layer, ['sort_order' => 10, 'show_in_legend' => true]);

        $this->getJson(route('geo-viewers.config', $viewer))
            ->assertOk()
            ->assertJsonPath('layers.0.access_policy', 'view_only')
            ->assertJsonPath('layers.0.download.allowed', false)
            ->assertJsonMissingPath('layers.0.download.url');
    }

    public function test_managed_geodata_layer_enables_all_public_attributes_without_changing_external_layers(): void
    {
        $viewer = GeoViewer::factory()->published()->create();
        $managed = GeoLayer::factory()->create([
            'slug' => 'centros-de-salud',
            'source_type' => 'geojson',
            'source_url' => '/api/public/geodata/centros-de-salud',
            'popup_fields' => [],
        ]);
        $external = GeoLayer::factory()->create([
            'slug' => 'referencia-externa',
            'source_type' => 'geojson',
            'source_url' => 'https://datos.example.org/referencia.geojson',
            'popup_fields' => ['nombre'],
        ]);
        $viewer->layers()->attach($managed, ['sort_order' => 1]);
        $viewer->layers()->attach($external, ['sort_order' => 2]);

        $this->getJson(route('geo-viewers.config', $viewer))
            ->assertOk()
            ->assertJsonPath('layers.0.popup_all_attributes', true)
            ->assertJsonPath('layers.0.source.url', '/api/public/geodata/centros-de-salud?revision='.$managed->updated_at->getTimestamp())
            ->assertJsonPath('layers.0.download.url', '/api/public/geodata/centros-de-salud?revision='.$managed->updated_at->getTimestamp())
            ->assertJsonPath('layers.0.popup_fields', [])
            ->assertJsonPath('layers.1.popup_all_attributes', false)
            ->assertJsonPath('layers.1.source.url', 'https://datos.example.org/referencia.geojson')
            ->assertJsonPath('layers.1.popup_fields', ['nombre']);
    }

    public function test_embed_page_allows_only_configured_parent_sites(): void
    {
        $this->withoutVite();

        config()->set('geovisors.frame_ancestors', ["'self'", 'https://meta.gov.co']);
        $viewer = GeoViewer::factory()->published()->create(['name' => 'Visor público']);

        $response = $this->get(route('geo-viewers.embed', $viewer));

        $response->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://meta.gov.co;")
            ->assertHeaderMissing('X-Frame-Options')
            ->assertSee('data-geo-viewer', false)
            ->assertSee('data-config-url="/api/public/visores/', false)
            ->assertDontSee('data-config-url="http://', false)
            ->assertSee('Visor público');
    }

    public function test_embed_page_escapes_viewer_content(): void
    {
        $this->withoutVite();

        $viewer = GeoViewer::factory()->published()->create([
            'name' => '<script>alert("visor")</script>',
            'description' => '<img src=x onerror=alert(1)>',
        ]);

        $this->get(route('geo-viewers.embed', $viewer))
            ->assertOk()
            ->assertSee($viewer->name)
            ->assertDontSee('<script>alert("visor")</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_demo_page_embeds_published_viewer_and_excludes_drafts(): void
    {
        $this->withoutVite();

        $published = GeoViewer::factory()->published()->create([
            'name' => 'Gestión del riesgo',
            'slug' => 'gestion-del-riesgo',
        ]);
        GeoViewer::factory()->create(['name' => 'Visor reservado', 'slug' => 'reservado']);

        $this->get(route('geo-viewers.demo', ['visor' => $published->slug]))
            ->assertOk()
            ->assertSee('Página de prueba')
            ->assertSee(route('geo-viewers.embed', $published), false)
            ->assertSee('data-iframe-preview', false)
            ->assertDontSee('Visor reservado');
    }

    public function test_demo_page_explains_when_there_are_no_published_viewers(): void
    {
        $this->withoutVite();

        GeoViewer::factory()->create();

        $this->get(route('geo-viewers.demo'))
            ->assertOk()
            ->assertSee('Todavía no hay un geovisor publicado')
            ->assertDontSee('data-iframe-preview', false);
    }
}
