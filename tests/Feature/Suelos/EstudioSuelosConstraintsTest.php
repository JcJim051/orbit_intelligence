<?php

namespace Tests\Feature\Suelos;

use App\Models\InvestmentContract;
use App\Models\InvestmentProject;
use App\Models\Suelos\ClasificacionUscs;
use App\Models\Suelos\Ensayo;
use App\Models\Suelos\Estrato;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\EstudioValidacionHistorial;
use App\Models\Suelos\Exploracion;
use App\Models\Suelos\ParametrosDiseno;
use App\Models\Suelos\TipoEnsayo;
use App\Models\User;
use Database\Seeders\SuelosCatalogoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\RequiresPostgis;
use Tests\TestCase;

#[Group('postgis')]
class EstudioSuelosConstraintsTest extends TestCase
{
    use RefreshDatabase, RequiresPostgis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRequiresPostgis();
        $this->seed(SuelosCatalogoSeeder::class);
    }

    public function test_exploration_normalizes_coordinates_to_point_z_9377_and_links_catalogs(): void
    {
        $estudio = EstudioSuelos::factory()->create();
        $exploracion = Exploracion::factory()->create([
            'estudio_id' => $estudio->id,
            'cota_msnm' => 520,
        ]);
        $estrato = Estrato::factory()->create(['exploracion_id' => $exploracion->id]);
        $parametros = ParametrosDiseno::factory()->create(['estudio_id' => $estudio->id]);
        $historial = EstudioValidacionHistorial::query()->create([
            'estudio_id' => $estudio->id,
            'estado_anterior_id' => null,
            'estado_nuevo_id' => $estudio->estado_validacion_id,
            'usuario_id' => $estudio->cargado_por,
            'observacion' => 'Cargue inicial',
        ]);

        $geometria = DB::selectOne(
            'select ST_SRID(geom) as srid, ST_X(geom) as x, ST_Y(geom) as y, ST_Z(geom) as z from suelos.exploracion where id = ?',
            [$exploracion->id],
        );
        $resumen = DB::selectOne(
            'select entidad, municipio, uscs_predominante from suelos.v_exploraciones_resumen where exploracion_id = ?',
            [$exploracion->id],
        );

        $this->assertSame(9377, (int) $geometria->srid);
        $this->assertGreaterThan(4_000_000, (float) $geometria->x);
        $this->assertLessThan(6_000_000, (float) $geometria->x);
        $this->assertGreaterThan(1_000_000, (float) $geometria->y);
        $this->assertLessThan(3_000_000, (float) $geometria->y);
        $this->assertEqualsWithDelta(520, (float) $geometria->z, 0.01);
        $this->assertSame($estudio->dependencia->etiqueta(), $resumen->entidad);
        $this->assertSame($estudio->municipio->codigo_dane.' — '.$estudio->municipio->nombre, $resumen->municipio);
        $this->assertSame('CL', $resumen->uscs_predominante);
        $this->assertTrue($estudio->exploraciones()->whereKey($exploracion->id)->exists());
        $this->assertTrue($estudio->estratos()->whereKey($estrato->id)->exists());
        $this->assertTrue($estudio->parametrosDiseno()->whereKey($parametros->id)->exists());
        $this->assertTrue($estudio->historialValidacion()->whereKey($historial->id)->exists());
        $this->assertSame('CL — Arcilla de baja plasticidad', ClasificacionUscs::query()->where('codigo', 'CL')->firstOrFail()->etiqueta());
    }

    public function test_validated_studies_are_the_only_ones_visible_in_the_report_scope(): void
    {
        $visible = EstudioSuelos::factory()->validado()->create();
        $borrador = EstudioSuelos::factory()->create();

        $ids = EstudioSuelos::query()->visibleEnInforme()->pluck('id');

        $this->assertTrue($ids->contains($visible->id));
        $this->assertFalse($ids->contains($borrador->id));
    }

    public function test_dependency_scope_keeps_studies_of_other_entities_out(): void
    {
        $propio = EstudioSuelos::factory()->create();
        EstudioSuelos::factory()->create();

        $ids = EstudioSuelos::query()->deDependencia($propio->dependencia_id)->pluck('id');

        $this->assertSame([(int) $propio->id], $ids->map(fn (mixed $id): int => (int) $id)->all());
    }

    public function test_same_consultant_and_date_without_a_bpin_is_rejected_as_a_duplicate(): void
    {
        EstudioSuelos::factory()->create([
            'consultor_nombre' => 'Firma repetida S.A.S.',
            'fecha_estudio' => '2024-06-01',
        ]);

        $this->expectException(QueryException::class);

        EstudioSuelos::factory()->create([
            'consultor_nombre' => 'Firma repetida S.A.S.',
            'fecha_estudio' => '2024-06-01',
        ]);
    }

    public function test_study_without_bpin_rejects_a_short_justification(): void
    {
        $this->expectException(QueryException::class);

        EstudioSuelos::factory()->create([
            'investment_project_id' => null,
            'sin_bpin_justificacion' => 'sin soporte',
        ]);
    }

    public function test_contract_from_another_project_is_rejected(): void
    {
        $proyecto = InvestmentProject::factory()->create();
        $otro = InvestmentProject::factory()->create();
        $contrato = InvestmentContract::query()->create([
            'investment_project_id' => $otro->id,
            'source_dataset_id' => 'uwns-mbwd',
            'source_row_hash' => hash('sha256', 'contrato-otro-proyecto'),
            'reference' => 'CO1.PCCNTR.0000001',
            'object' => 'Consultoría de estudios de suelos',
            'raw_data' => [],
        ]);

        $this->expectException(QueryException::class);

        EstudioSuelos::factory()->create([
            'investment_project_id' => $proyecto->id,
            'investment_contract_id' => $contrato->id,
            'sin_bpin_justificacion' => null,
        ]);
    }

    public function test_matching_project_contract_is_stored(): void
    {
        $proyecto = InvestmentProject::factory()->create();
        $contrato = InvestmentContract::query()->create([
            'investment_project_id' => $proyecto->id,
            'source_dataset_id' => 'uwns-mbwd',
            'source_row_hash' => hash('sha256', 'contrato-mismo-proyecto'),
            'reference' => 'CO1.PCCNTR.0000002',
            'object' => 'Consultoría de estudios de suelos',
            'raw_data' => [],
        ]);

        $estudio = EstudioSuelos::factory()->create([
            'investment_project_id' => $proyecto->id,
            'investment_contract_id' => $contrato->id,
            'sin_bpin_justificacion' => null,
        ]);

        $this->assertTrue($estudio->proyecto->is($proyecto));
        $this->assertTrue($estudio->contrato->is($contrato));
        $this->assertTrue($proyecto->estudiosSuelos()->whereKey($estudio->id)->exists());
    }

    public function test_overlapping_strata_are_rejected(): void
    {
        $exploracion = Exploracion::factory()->create();
        Estrato::factory()->create([
            'exploracion_id' => $exploracion->id,
            'profundidad_desde_m' => 0,
            'profundidad_hasta_m' => 2,
        ]);

        $this->expectException(QueryException::class);

        Estrato::factory()->create([
            'exploracion_id' => $exploracion->id,
            'profundidad_desde_m' => 1.5,
            'profundidad_hasta_m' => 3,
        ]);
    }

    public function test_stratum_deeper_than_the_exploration_is_rejected(): void
    {
        $exploracion = Exploracion::factory()->create(['profundidad_total_m' => 6]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('Profundidad');

        Estrato::factory()->create([
            'exploracion_id' => $exploracion->id,
            'profundidad_desde_m' => 0,
            'profundidad_hasta_m' => 6.5,
        ]);
    }

    public function test_water_table_without_a_finding_is_rejected(): void
    {
        $this->expectException(QueryException::class);

        Exploracion::factory()->create([
            'nivel_freatico_encontrado' => false,
            'nivel_freatico_m' => 1,
        ]);
    }

    public function test_point_outside_the_loaded_meta_boundary_is_rejected(): void
    {
        $estudio = EstudioSuelos::factory()->create();
        DB::insert(
            "insert into suelos.limite_municipio (municipio_id, geom) values (?, ST_SetSRID(ST_GeomFromText('MULTIPOLYGON(((100 100, 100 200, 200 200, 200 100, 100 100)))'), 9377))",
            [$estudio->municipio_id],
        );

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('fuera del departamento del Meta');

        Exploracion::factory()->create(['estudio_id' => $estudio->id]);
    }

    public function test_quality_view_flags_implausible_values_and_inconsistent_plasticity(): void
    {
        $exploracion = Exploracion::factory()->create();
        $humedad = TipoEnsayo::query()->where('codigo', 'W_NAT')->firstOrFail();
        $liquido = TipoEnsayo::query()->where('codigo', 'LL')->firstOrFail();
        $plastico = TipoEnsayo::query()->where('codigo', 'LP')->firstOrFail();
        $indice = TipoEnsayo::query()->where('codigo', 'IP')->firstOrFail();

        Ensayo::factory()->create([
            'exploracion_id' => $exploracion->id,
            'tipo_ensayo_id' => $humedad->id,
            'profundidad_desde_m' => 1,
            'valor' => 600,
        ]);
        Ensayo::factory()->create([
            'exploracion_id' => $exploracion->id,
            'tipo_ensayo_id' => $liquido->id,
            'profundidad_desde_m' => 2,
            'valor' => 40,
            'muestra' => 'M-1',
        ]);
        Ensayo::factory()->create([
            'exploracion_id' => $exploracion->id,
            'tipo_ensayo_id' => $plastico->id,
            'profundidad_desde_m' => 2,
            'valor' => 20,
            'muestra' => 'M-1',
        ]);
        Ensayo::factory()->create([
            'exploracion_id' => $exploracion->id,
            'tipo_ensayo_id' => $indice->id,
            'profundidad_desde_m' => 2,
            'valor' => 30,
            'muestra' => 'M-1',
        ]);

        $alertas = DB::table('suelos.v_alertas_calidad')
            ->where('exploracion_id', $exploracion->id)
            ->pluck('alerta');

        $this->assertTrue($alertas->contains('ENSAYO_FUERA_DE_RANGO'));
        $this->assertTrue($alertas->contains('IP_DISTINTO_LL_MENOS_LP'));
    }

    public function test_loaded_by_user_sees_the_study_from_the_user_relation(): void
    {
        $usuario = User::factory()->create();
        $estudio = EstudioSuelos::factory()->create(['cargado_por' => $usuario->id]);

        $this->assertTrue($usuario->estudiosSuelosCargados()->whereKey($estudio->id)->exists());
    }
}
