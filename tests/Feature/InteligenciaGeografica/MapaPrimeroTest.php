<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\ModoAnalisis;
use App\Enums\OrigenGeometriaAnalisis;
use App\Enums\UserRole;
use App\Filament\Pages\VisorCapas;
use App\Filament\Resources\AnalisisAreaResource;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaObjetoCache;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Models\User;
use App\Services\InteligenciaGeografica\CrearAnalisisArea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class MapaPrimeroTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->withoutVite();
    }

    public function test_processing_a_polygon_stores_it_in_9377_and_reports_the_intersected_source(): void
    {
        $poligono = '{"type":"Polygon","coordinates":[[[-73.78,3.97],[-73.75,3.97],[-73.75,4.00],[-73.78,4.00],[-73.78,3.97]]]}';
        $cita = 'Entidad de prueba. Capa cruzada. https://ejemplo.test/capa. Licencia de prueba.';
        $capa = Capa::factory()->create([
            'codigo' => 'CAPA-CRUCE',
            'nombre' => 'Capa con copia',
            'fecha_corte_fuente' => '2026-02-28 00:00:00',
            'cita_fuente' => $cita,
            'url_servicio' => 'https://ejemplo.test/capa',
            'licencia' => 'Licencia de prueba',
            'activa' => true,
        ]);
        CapaSincronizacion::factory()->create(['capa_id' => $capa->id]);
        CapaObjetoCache::factory()->create([
            'capa_id' => $capa->id,
            'identificador_origen' => 'predio-1',
            'atributos' => ['nombre' => 'Predio de prueba'],
            'geom' => DB::raw("ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON('{$poligono}'), 4326), 9377)"),
        ]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $analisis = app(CrearAnalisisArea::class)->crear($admin, ModoAnalisis::Normal, null, $poligono, null, null);
        $geometria = DB::selectOne(
            'select ST_SRID(geom) as srid from inteligencia.analisis_area where id = ?',
            [$analisis->id],
        );
        $resultado = $analisis->resultados()->where('capa_id', $capa->id)->first();

        $this->assertSame(9377, (int) $geometria->srid);
        $this->assertNotNull($resultado);
        $this->assertGreaterThan(0, $resultado->conteo);
        $this->assertGreaterThan(0, (float) $resultado->area_m2);

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('index', ['analisis' => $analisis->getKey()]))
            ->assertOk()
            ->assertSee('Procesar')
            ->assertSee('CAPA-CRUCE — Capa con copia')
            ->assertSee($cita)
            ->assertSee('predio-1')
            ->assertSee('ha')
            ->assertSee('%');

        $this->actingAs($admin)
            ->getJson(route('inteligencia.capas.geojson', ['capa' => $capa->id]).'?bbox=-73.9,3.9,-73.7,4.1')
            ->assertOk()
            ->assertJsonPath('features.0.properties.identificador', 'predio-1')
            ->assertJsonPath('pendiente', false);
    }

    public function test_a_layer_without_a_copy_stays_pending_on_the_viewer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $capa = Capa::factory()->create([
            'codigo' => 'CAPA-VACIA',
            'nombre' => 'Capa sin copia',
            'activa' => true,
        ]);

        $this->actingAs($admin)
            ->get(VisorCapas::getUrl())
            ->assertOk()
            ->assertSee('CAPA-VACIA — Capa sin copia')
            ->assertSee('Pendiente de conector')
            ->assertSee('Visor de capas');

        $this->actingAs($admin)
            ->getJson(route('inteligencia.capas.geojson', ['capa' => $capa->id]).'?bbox=-74.2,3.2,-72.4,4.6')
            ->assertOk()
            ->assertJsonPath('features', [])
            ->assertJsonPath('pendiente', true);

        $this->actingAs($admin)
            ->getJson(route('inteligencia.capas.geojson', ['capa' => $capa->id]).'?bbox=-80,0,-60,10')
            ->assertUnprocessable();
    }

    public function test_a_polygon_declared_in_9377_is_stored_without_treating_it_as_degrees(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $geoJson = json_encode([
            'type' => 'Polygon',
            'coordinates' => [[[4916000, 1998000], [4916100, 1998000], [4916100, 1998100], [4916000, 1998100], [4916000, 1998000]]],
        ], JSON_THROW_ON_ERROR);

        $analisis = app(CrearAnalisisArea::class)->crear(
            $admin,
            ModoAnalisis::Normal,
            null,
            $geoJson,
            'predios.zip',
            null,
            9377,
            OrigenGeometriaAnalisis::Shapefile,
        );

        $fila = DB::selectOne(
            'select ST_SRID(geom) as srid, round((ST_Area(geom) / 10000)::numeric, 2) as hectareas from inteligencia.analisis_area where id = ?',
            [$analisis->id],
        );

        $this->assertSame(9377, (int) $fila->srid);
        $this->assertEqualsWithDelta(1.0, (float) $fila->hectareas, 0.05);
        $this->assertSame('shapefile', $analisis->origen_geometria->value);
    }
}
