<?php

namespace Tests\Feature\Admin;

use App\Models\GeoLayer;
use App\Models\GeoViewer;
use App\Services\Dashboards\DashboardRelationshipDiagnostic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardRelationshipDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_the_managed_layer_that_publishes_the_join_field(): void
    {
        $viewer = GeoViewer::factory()->create();
        $first = GeoLayer::factory()->create([
            'source_url' => '/api/public/geodata/departamentos',
            'public_attribute_fields' => ['depto'],
        ]);
        $municipal = GeoLayer::factory()->create([
            'source_url' => '/api/public/geodata/limite-municipal-meta',
            'public_attribute_fields' => ['mp_codigo', 'mp_nombre'],
        ]);
        $viewer->layers()->attach($first, ['sort_order' => 10]);
        $viewer->layers()->attach($municipal, ['sort_order' => 20]);

        $layer = (new DashboardRelationshipDiagnostic)->managedLayerForField($viewer, 'mp_codigo');

        $this->assertSame($municipal->id, $layer->id);
    }
}
