<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\OpenDataSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenDataSnapshotFallbackTest extends TestCase
{
    use RefreshDatabase;

    private bool $schemaChanged = false;

    public function test_schema_change_keeps_last_valid_snapshot_and_marks_source_for_review(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/views/abcd-1234')) {
                return Http::response(['name' => 'Puntos', 'columns' => $this->schemaChanged ? [
                    ['fieldName' => 'latitud', 'name' => 'Latitud', 'dataTypeName' => 'text'],
                    ['fieldName' => 'longitud', 'name' => 'Longitud', 'dataTypeName' => 'number'],
                ] : [
                    ['fieldName' => 'latitud', 'name' => 'Latitud', 'dataTypeName' => 'number'],
                    ['fieldName' => 'longitud', 'name' => 'Longitud', 'dataTypeName' => 'number'],
                    ['fieldName' => 'nombre', 'name' => 'Nombre', 'dataTypeName' => 'text'],
                    ['fieldName' => 'valor', 'name' => 'Valor', 'dataTypeName' => 'number'],
                ]]);
            }
            if (str_contains($request->url(), '/resource/abcd-1234.json')) {
                if (($request->data()['$select'] ?? null) === 'count(*) as total') return Http::response([['total' => '2']]);

                return Http::response([
                    ['latitud' => '4.15', 'longitud' => '-73.63', 'nombre' => 'A', 'valor' => '1'],
                    ['latitud' => '4.20', 'longitud' => '-73.70', 'nombre' => 'B', 'valor' => '2'],
                ]);
            }

            return Http::response([], 404);
        });

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin)->postJson(route('admin.open-data-sources.store'), [
            'url' => 'https://www.datos.gov.co/d/abcd-1234', 'name' => 'Puntos', 'slug' => 'puntos',
            'attribution' => 'Entidad', 'metric_field' => 'valor', 'aggregation' => 'sum',
            'popup_fields' => ['nombre', 'valor'], 'filters' => [], 'palette' => 'green',
            'no_data_color' => '#d1d5db', 'opacity' => .75, 'target' => 'new',
            'viewer_name' => 'Visor puntos', 'viewer_slug' => 'visor-puntos',
        ])->assertCreated();
        $source = OpenDataSource::firstOrFail();
        $this->schemaChanged = true;

        $this->actingAs($admin)->getJson(route('admin.open-data-sources.preview', $source).'?force=1')
            ->assertOk()->assertJsonPath('metadata.stale', true)->assertJsonCount(2, 'features');
        $this->assertSame('needs_review', $source->fresh()->status);
    }
}
