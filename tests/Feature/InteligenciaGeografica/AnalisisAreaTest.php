<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\EstadoAnalisisArea;
use App\Enums\OrigenGeometriaAnalisis;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\CapaObjetoCache;
use App\Models\InteligenciaGeografica\CapaRefresco;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class AnalisisAreaTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
    }

    public function test_drawn_geojson_is_stored_as_a_multipolygon_in_9377(): void
    {
        $analisis = AnalisisArea::crearConGeoJson([
            'user_id' => User::factory()->create()->id,
            'origen_geometria' => OrigenGeometriaAnalisis::Dibujo,
            'estado' => EstadoAnalisisArea::Borrador,
        ], '{"type":"Polygon","coordinates":[[[-73.78,3.97],[-73.75,3.97],[-73.75,4.00],[-73.78,4.00],[-73.78,3.97]]]}');

        $geometria = DB::selectOne(
            'select ST_SRID(geom) as srid, GeometryType(geom) as tipo from inteligencia.analisis_area where id = ?',
            [$analisis->id],
        );

        $this->assertSame(9377, (int) $geometria->srid);
        $this->assertSame('MULTIPOLYGON', $geometria->tipo);
        $this->assertTrue($analisis->usuario()->exists());
    }

    public function test_result_cache_and_refresh_log_belong_to_the_requested_layer(): void
    {
        $analisis = AnalisisArea::factory()->create();
        $resultado = AnalisisResultado::factory()->create([
            'analisis_area_id' => $analisis->id,
        ]);
        $objeto = CapaObjetoCache::factory()->create([
            'capa_id' => $resultado->capa_id,
        ]);
        $refresco = CapaRefresco::factory()->create([
            'capa_id' => $resultado->capa_id,
        ]);

        $resultado->refresh();
        $capa = $resultado->capa;

        $this->assertTrue($analisis->resultados()->whereKey($resultado->id)->exists());
        $this->assertTrue($capa->is($resultado->capa));
        $this->assertTrue($capa->objetosCache()->whereKey($objeto->id)->exists());
        $this->assertTrue($capa->refrescos()->whereKey($refresco->id)->exists());

        $cache = DB::selectOne(
            'select ST_SRID(geom) as srid from inteligencia.capa_objeto_cache where id = ?',
            [$objeto->id],
        );
        $this->assertSame(9377, (int) $cache->srid);
    }
}
