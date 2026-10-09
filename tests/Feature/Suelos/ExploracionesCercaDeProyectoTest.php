<?php

namespace Tests\Feature\Suelos;

use App\Models\InvestmentProject;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\Exploracion;
use App\Models\User;
use Database\Seeders\SuelosCatalogoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class ExploracionesCercaDeProyectoTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->seed(SuelosCatalogoSeeder::class);
    }

    public function test_default_radius_returns_a_validated_exploration_between_100_and_500_meters(): void
    {
        [$proyecto, $exploracion] = $this->exploracionValidada();
        $this->poligonoTrasladado($proyecto, $exploracion, 200);

        $this->assertCount(0, Exploracion::cercaDeProyecto($proyecto->bpin, 100));

        $filas = Exploracion::cercaDeProyecto($proyecto->bpin);

        $this->assertCount(1, $filas);
        $this->assertSame($exploracion->id, (int) $filas[0]->exploracion_id);
        $this->assertSame('2. 100 a 500 m', $filas[0]->franja);
        $this->assertGreaterThan(100, (float) $filas[0]->distancia_m);
        $this->assertLessThan(500, (float) $filas[0]->distancia_m);
    }

    public function test_exploration_inside_the_polygon_is_reported_in_band_zero(): void
    {
        [$proyecto, $exploracion] = $this->exploracionValidada();
        $this->poligonoTrasladado($proyecto, $exploracion, 0);

        $filas = Exploracion::cercaDeProyecto($proyecto->bpin, 500);

        $this->assertCount(1, $filas);
        $this->assertSame('0. Dentro del polígono', $filas[0]->franja);
        $this->assertSame(0.0, (float) $filas[0]->distancia_m);
    }

    public function test_draft_exploration_is_omitted_from_the_project_query(): void
    {
        $proyecto = InvestmentProject::factory()->create();
        $estudio = EstudioSuelos::factory()->create([
            'investment_project_id' => $proyecto->id,
            'sin_bpin_justificacion' => null,
        ]);
        $exploracion = Exploracion::factory()->create(['estudio_id' => $estudio->id]);
        $this->poligonoTrasladado($proyecto, $exploracion, 0);

        $this->assertCount(0, Exploracion::cercaDeProyecto($proyecto->bpin, 500));
    }

    /**
     * @return array{0: InvestmentProject, 1: Exploracion}
     */
    private function exploracionValidada(): array
    {
        $proyecto = InvestmentProject::factory()->create();
        $estudio = EstudioSuelos::factory()->validado()->create([
            'investment_project_id' => $proyecto->id,
            'sin_bpin_justificacion' => null,
        ]);
        $exploracion = Exploracion::factory()->create(['estudio_id' => $estudio->id]);

        return [$proyecto, $exploracion];
    }

    private function poligonoTrasladado(InvestmentProject $proyecto, Exploracion $exploracion, int $metrosAlEste): void
    {
        DB::insert(
            'insert into inteligencia.analisis_area
                (investment_project_id, user_id, geom, origen_geometria, fecha, estado, created_at, updated_at)
             values (
                ?,
                ?,
                ST_Multi(ST_Buffer(ST_Translate((select ST_Force2D(geom) from suelos.exploracion where id = ?), ?, 0), 15)),
                ?,
                ?,
                ?,
                now(),
                now()
             )',
            [$proyecto->id, User::factory()->create()->id, $exploracion->id, $metrosAlEste, 'dibujo', '2026-03-01 12:00:00', 'listo'],
        );
    }
}
