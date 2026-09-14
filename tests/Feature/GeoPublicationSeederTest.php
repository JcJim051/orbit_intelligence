<?php

namespace Tests\Feature;

use App\Enums\GeoViewerStatus;
use App\Models\GeoLayer;
use App\Models\GeoViewer;
use Database\Seeders\GeoPublicationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeoPublicationSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The demonstrative catalogue must remain reproducible and unpublished.
     */
    public function test_it_creates_three_related_draft_viewers_and_their_layers(): void
    {
        $this->seed(GeoPublicationSeeder::class);

        $this->assertDatabaseCount('geo_viewers', 3);
        $this->assertDatabaseCount('geo_layers', 4);
        $this->assertDatabaseCount('geo_viewer_layers', 6);
        $this->assertSame(3, GeoViewer::query()->where('status', GeoViewerStatus::Draft)->count());

        $riskViewer = GeoViewer::query()->where('slug', 'gestion-riesgo-meta')->firstOrFail();
        $this->assertSame(
            ['corredores-hidricos-demo', 'puntos-criticos-demo'],
            $riskViewer->layers()->orderBy('geo_layers.slug')->pluck('geo_layers.slug')->all(),
        );

        $criticalPoints = GeoLayer::query()->where('slug', 'puntos-criticos-demo')->firstOrFail();
        $this->assertSame('/data/geovisores/puntos-criticos-demo.geojson', $criticalPoints->source_url);
        $boundaries = GeoLayer::query()->where('slug', 'limites-municipales-meta')->firstOrFail();
        $this->assertSame('/api/public/geodata/limites-municipales-meta', $boundaries->source_url);
    }

    public function test_demonstrative_geojson_files_are_valid_feature_collections(): void
    {
        foreach (['puntos-criticos-demo', 'corredores-hidricos-demo', 'indicadores-municipales-demo'] as $file) {
            $contents = file_get_contents(public_path("data/geovisores/{$file}.geojson"));
            $geojson = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

            $this->assertSame('FeatureCollection', $geojson['type']);
            $this->assertNotEmpty($geojson['features']);
        }
    }
}
