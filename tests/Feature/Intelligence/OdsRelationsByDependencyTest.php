<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\Actividad;
use App\Models\ActividadProgramacion;
use App\Models\Dependencia;
use App\Models\EjecucionFinanciera;
use App\Models\FuenteFinanciacion;
use App\Models\IndicadorResultado;
use App\Models\IndicadorResultadoOdsLink;
use App\Models\IndicadorResultadoOdsReview;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\OdsGoal;
use App\Models\OdsIndicator;
use App\Models\OdsTarget;
use App\Models\Proyecto;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class OdsRelationsByDependencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_it_groups_ods_relations_by_the_dependency_of_each_product_goal(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dependency = Dependencia::factory()->create([
            'sigla' => 'DIGERD',
            'nombre' => 'Dirección de Gestión del Riesgo',
        ]);
        Dependencia::factory()->create([
            'sigla' => 'SIN-ODS',
            'nombre' => 'Dependencia sin relaciones ODS',
        ]);
        $resultIndicator = IndicadorResultado::factory()->create([
            'codigo' => 'IR-ODS-01',
            'nombre' => 'Capacidad territorial fortalecida',
        ]);
        $resultGoal = MetaResultado::factory()->create([
            'indicador_resultado_id' => $resultIndicator->id,
            'codigo_provisional' => 'MR-ODS-01',
            'descripcion' => 'Reducir la vulnerabilidad territorial',
        ]);
        MetaProducto::factory()->create([
            'meta_resultado_id' => $resultGoal->id,
            'dependencia_id' => $dependency->id,
            'codigo' => '61071024506',
            'nombre' => 'Realizar intervenciones correctivas y prospectivas',
        ]);
        $goal = OdsGoal::query()->create(['code' => '11', 'name' => 'Ciudades y comunidades sostenibles']);
        $target = OdsTarget::query()->create(['ods_goal_id' => $goal->id, 'code' => '11.5', 'name' => 'Reducir el impacto de los desastres']);
        $odsIndicator = OdsIndicator::query()->create(['ods_target_id' => $target->id, 'code' => '11.5.1', 'name' => 'Personas afectadas por desastres']);
        $review = IndicadorResultadoOdsReview::query()->create([
            'indicador_resultado_id' => $resultIndicator->id,
            'status' => 'completed',
        ]);
        IndicadorResultadoOdsLink::query()->create([
            'review_id' => $review->id,
            'indicador_resultado_id' => $resultIndicator->id,
            'ods_indicator_id' => $odsIndicator->id,
            'relation_type' => 'direct',
            'confidence' => 'high',
            'status' => 'accepted',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(Workspace::getUrl(['workspace' => 'ods-relaciones-dependencias']))
            ->assertOk()
            ->assertSee('Indicadores ODS por dependencia')
            ->assertSee('Dirección de Gestión del Riesgo')
            ->assertSee('11.5.1')
            ->assertSee('61071024506')
            ->assertSee('Capacidad territorial fortalecida')
            ->assertSee('Dependencia sin relaciones ODS')
            ->assertSee('Sin relaciones');
    }

    public function test_the_public_wrapper_redirects_to_the_dependency_workspace(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('intelligence.indicadores-ods.dependencias'))
            ->assertRedirect(Workspace::getUrl(['workspace' => 'ods-relaciones-dependencias']));
    }

    public function test_it_downloads_the_detailed_and_financial_ods_workbook(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dependency = Dependencia::factory()->create(['codigo' => '04', 'sigla' => 'DIGERD']);
        $project = Proyecto::factory()->create();
        $resultIndicator = IndicadorResultado::factory()->create(['codigo' => 'IR-01']);
        $resultGoal = MetaResultado::factory()->create([
            'indicador_resultado_id' => $resultIndicator->id,
            'codigo_provisional' => 'MR-01',
        ]);
        $product = MetaProducto::factory()->create([
            'meta_resultado_id' => $resultGoal->id,
            'dependencia_id' => $dependency->id,
            'codigo' => '61071024506',
        ]);
        $goal = OdsGoal::query()->create(['code' => '11', 'name' => 'Ciudades sostenibles']);
        $target = OdsTarget::query()->create(['ods_goal_id' => $goal->id, 'code' => '11.5', 'name' => 'Reducir impactos']);
        $odsIndicator = OdsIndicator::query()->create(['ods_target_id' => $target->id, 'code' => '11.5.1', 'name' => 'Personas afectadas']);
        $review = IndicadorResultadoOdsReview::query()->create([
            'indicador_resultado_id' => $resultIndicator->id,
            'status' => 'completed',
        ]);
        IndicadorResultadoOdsLink::query()->create([
            'review_id' => $review->id,
            'indicador_resultado_id' => $resultIndicator->id,
            'ods_indicator_id' => $odsIndicator->id,
            'relation_type' => 'direct',
            'confidence' => 'high',
            'status' => 'accepted',
            'created_by' => $admin->id,
        ]);

        $followUp = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 8]);
        $source = FuenteFinanciacion::factory()->create();
        $activity = Actividad::factory()->create([
            'proyecto_id' => $project->id,
            'dependencia_id' => $dependency->id,
            'meta_producto_id' => $product->id,
        ]);
        ActividadProgramacion::query()->create([
            'actividad_id' => $activity->id,
            'fuente_financiacion_id' => $source->id,
            'vigencia' => 2026,
            'valor_asignado' => 1000,
        ]);
        $report = ReporteProyecto::query()->create([
            'seguimiento_id' => $followUp->id,
            'proyecto_id' => $project->id,
            'dependencia_id' => $dependency->id,
        ]);
        EjecucionFinanciera::query()->create([
            'reporte_proyecto_id' => $report->id,
            'actividad_id' => $activity->id,
            'fuente_financiacion_id' => $source->id,
            'comprometido' => 750,
            'obligado' => 500,
            'pagado' => 250,
        ]);

        $response = $this->actingAs($admin)->get(route('intelligence.indicadores-ods.dependencias.download', [
            'seguimiento' => $followUp->id,
        ]));

        $response->assertOk()->assertDownload('informe-ods-dependencias-2026-08.xlsx');
        $path = $response->baseResponse->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $sheets[$sheet->getName()] = collect(iterator_to_array($sheet->getRowIterator()))
                ->map(fn ($row): array => $row->toArray());
        }
        $reader->close();

        $this->assertSame(['ODS por dependencia', 'Resumen financiero'], array_keys($sheets));
        $this->assertContains('61071024506', $sheets['ODS por dependencia']->flatten()->all());
        $this->assertContains(1000, $sheets['ODS por dependencia']->flatten()->all());
        $this->assertContains(750, $sheets['Resumen financiero']->flatten()->all());
    }
}
