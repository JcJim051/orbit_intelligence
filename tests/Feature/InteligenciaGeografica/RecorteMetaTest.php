<?php

namespace Tests\Feature\InteligenciaGeografica;

use App\Enums\ModoAnalisis;
use App\Enums\UserRole;
use App\Filament\Resources\AnalisisAreaResource;
use App\Filament\Resources\AnalisisAreaResource\Pages\MapaAnalisis;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\User;
use App\Services\InteligenciaGeografica\CrearAnalisisArea;
use App\Services\InteligenciaGeografica\RecorteMeta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class RecorteMetaTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->withoutVite();
    }

    public function test_the_official_meta_boundary_is_stored_with_its_source_and_cutoff(): void
    {
        $limite = DB::selectOne(
            "select codigo, url_fuente, cita_fuente,
                    fecha_corte at time zone 'UTC' as corte_utc,
                    ST_Area(geom) / 1000000 as km2
             from inteligencia.limite_referencia
             where codigo = ?",
            [RecorteMeta::CODIGO],
        );

        $this->assertNotNull($limite);
        $this->assertSame('META', $limite->codigo);
        $this->assertSame(
            'https://geoportal.dane.gov.co/mparcgis/rest/services/MGN2025/Serv_CapasMGN_2025/FeatureServer/319',
            $limite->url_fuente,
        );
        $this->assertStringContainsString('Marco Geoestadístico Nacional versión 2025', $limite->cita_fuente);
        $this->assertStringContainsString('Departamento Administrativo Nacional de Estadística DANE', $limite->cita_fuente);
        $this->assertSame('2025-12-10 05:00:00', substr((string) $limite->corte_utc, 0, 19));
        $this->assertGreaterThan(80000, (float) $limite->km2);
        $this->assertLessThan(90000, (float) $limite->km2);
    }

    public function test_a_polygon_inside_meta_keeps_its_area_and_reports_nothing_outside(): void
    {
        $analisis = $this->procesar('[[[-73.78,3.97],[-73.75,3.97],[-73.75,4.00],[-73.78,4.00],[-73.78,3.97]]]');
        $areas = DB::selectOne(
            'select ST_Area(geom) as analizada, ST_Area(geometria_original) as original
             from inteligencia.analisis_area where id = ?',
            [$analisis->id],
        );

        $this->assertEqualsWithDelta((float) $areas->original, (float) $areas->analizada, 1);
        $this->assertEqualsWithDelta(0, (float) $analisis->ha_fuera_meta, 0.0001);
        $this->assertEqualsWithDelta(0, (float) $analisis->pct_fuera_meta, 0.001);
        $this->assertNull($analisis->textoFueraDelMeta());
    }

    public function test_a_polygon_entirely_outside_meta_blocks_processing(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $bogota = '{"type":"Polygon","coordinates":[[[-74.15,4.60],[-74.05,4.60],[-74.05,4.70],[-74.15,4.70],[-74.15,4.60]]]}';

        try {
            app(CrearAnalisisArea::class)->crear($admin, ModoAnalisis::Normal, null, $bogota, null, null);
            $this->fail('El polígono de Bogotá debió bloquear el procesamiento.');
        } catch (ValidationException $excepcion) {
            $this->assertSame(RecorteMeta::MENSAJE_FUERA, $excepcion->validator->errors()->first('geojson'));
        }

        $this->assertSame(0, AnalisisArea::query()->count());

        $componente = Livewire::actingAs($admin)->test(MapaAnalisis::class)
            ->set('geojson', $bogota)
            ->call('procesar')
            ->assertSet('mensaje', RecorteMeta::MENSAJE_FUERA)
            ->assertSet('notaFuera', null);

        $this->assertMatchesRegularExpression('/<button[^>]*data-procesar-analisis[^>]*\sdisabled(?:="disabled")?(?=[\s>])/', $componente->html());

        $this->assertSame(0, AnalisisArea::query()->count());
    }

    public function test_processing_keeps_only_the_part_inside_meta_and_the_report_says_so(): void
    {
        $analisis = $this->procesar('[[[-74.2,4.0],[-73.6,4.0],[-73.6,4.25],[-74.2,4.25],[-74.2,4.0]]]');
        $analisis->refresh();
        $cruce = DB::selectOne(
            'select ST_Intersects(geom, ST_PointOnSurface(ST_Difference(geometria_original, geometria_analizada)))::int as toca_analizada,
                    ST_Intersects(geometria_original, ST_PointOnSurface(ST_Difference(geometria_original, geometria_analizada)))::int as toca_original,
                    ST_Area(geom) as analizada,
                    ST_Area(geometria_original) as original
             from inteligencia.analisis_area where id = ?',
            [$analisis->id],
        );

        $this->assertGreaterThan(0, (float) $analisis->ha_fuera_meta);
        $this->assertGreaterThan(0, (float) $analisis->pct_fuera_meta);
        $this->assertLessThan(100, (float) $analisis->pct_fuera_meta);
        $this->assertLessThan((float) $cruce->original, (float) $cruce->analizada);
        $this->assertSame(0, (int) $cruce->toca_analizada);
        $this->assertSame(1, (int) $cruce->toca_original);
        $this->assertStringContainsString('no se analizó porque las capas cubren solo el Meta', (string) $analisis->textoFueraDelMeta());

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(AnalisisAreaResource::getUrl('index', ['analisis' => $analisis->getKey()]))
            ->assertOk()
            ->assertSee('data-nota-fuera', false)
            ->assertSee('no se analizó porque las capas cubren solo el Meta')
            ->assertSee('DANE MGN 2025')
            ->assertSee('10/12/2025');

        $informe = view('filament.inteligencia.analisis', [
            'geojson' => $analisis->geoJson4326(),
            'geojsonFuera' => $analisis->geoJsonFuera4326(),
            'notaFuera' => $analisis->textoFueraDelMeta(),
            'filas' => [],
        ])->render();

        $this->assertStringContainsString('data-nota-fuera', $informe);
        $this->assertStringContainsString('no se analizó porque las capas cubren solo el Meta', $informe);
    }

    private function procesar(string $anillo): AnalisisArea
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        return app(CrearAnalisisArea::class)->crear(
            $admin,
            ModoAnalisis::Normal,
            null,
            '{"type":"Polygon","coordinates":'.$anillo.'}',
            null,
            null,
        );
    }
}
