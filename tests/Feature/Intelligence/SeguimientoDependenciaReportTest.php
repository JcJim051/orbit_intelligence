<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\Actividad;
use App\Models\AvanceFisico;
use App\Models\Dependencia;
use App\Models\MetaProducto;
use App\Models\PlanIndicativoMeta;
use App\Models\Proyecto;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeguimientoDependenciaReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_avance_fisico_de_la_vigencia_usa_toda_la_programacion_del_plan_indicativo(): void
    {
        [$admin, $dependencia, $seguimiento, $metaReportada] = $this->escenarioConAvance(11, 4);
        $metaPendiente = MetaProducto::factory()->create(['dependencia_id' => $dependencia->id]);
        PlanIndicativoMeta::query()->create([
            'meta_producto_id' => $metaReportada->id,
            'vigencia' => 2026,
            'valor_programado' => 4,
        ]);
        PlanIndicativoMeta::query()->create([
            'meta_producto_id' => $metaPendiente->id,
            'vigencia' => 2026,
            'valor_programado' => 36,
        ]);

        $response = $this->actingAs($admin)->get($this->reportUrl($dependencia, $seguimiento));

        $response
            ->assertSee('Avance físico vigencia')
            ->assertSee('27,5 %')
            ->assertSee('11,00 reportado / 40,00 programado');
    }

    public function test_porcentaje_de_avance_fisico_de_la_vigencia_no_supera_el_cien_por_ciento(): void
    {
        [$admin, $dependencia, $seguimiento, $metaReportada] = $this->escenarioConAvance(11, 4);
        PlanIndicativoMeta::query()->create([
            'meta_producto_id' => $metaReportada->id,
            'vigencia' => 2026,
            'valor_programado' => 4,
        ]);

        $response = $this->actingAs($admin)->get($this->reportUrl($dependencia, $seguimiento));

        $response
            ->assertSee('100,0 %')
            ->assertSee('11,00 reportado / 4,00 programado')
            ->assertDontSee('275,0 %');
    }

    public function test_corte_sin_plan_indicativo_conserva_la_programacion_de_actividades(): void
    {
        [$admin, $dependencia, $seguimiento] = $this->escenarioConAvance(2, 4);

        $response = $this->actingAs($admin)->get($this->reportUrl($dependencia, $seguimiento));

        $response
            ->assertSee('50,0 %')
            ->assertSee('2,00 reportado / 4,00 programado');
    }

    /**
     * @return array{User, Dependencia, Seguimiento, MetaProducto}
     */
    private function escenarioConAvance(float $avance, float $programadoActividad): array
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dependencia = Dependencia::factory()->create();
        $seguimiento = Seguimiento::factory()->create([
            'vigencia' => 2026,
            'mes' => 8,
            'created_by' => $admin->id,
        ]);
        $proyecto = Proyecto::factory()->create();
        $dependencia->proyectos()->attach($proyecto);
        $meta = MetaProducto::factory()->create(['dependencia_id' => $dependencia->id]);
        $proyecto->metasProducto()->attach($meta);
        $actividad = Actividad::factory()->create([
            'proyecto_id' => $proyecto->id,
            'dependencia_id' => $dependencia->id,
            'meta_producto_id' => $meta->id,
            'cantidad_programada' => $programadoActividad,
        ]);
        $reporte = ReporteProyecto::query()->create([
            'seguimiento_id' => $seguimiento->id,
            'proyecto_id' => $proyecto->id,
            'dependencia_id' => $dependencia->id,
        ]);
        AvanceFisico::query()->create([
            'reporte_proyecto_id' => $reporte->id,
            'actividad_id' => $actividad->id,
            'reportado_fisico' => true,
            'cantidad' => $avance,
        ]);

        return [$admin, $dependencia, $seguimiento, $meta];
    }

    private function reportUrl(Dependencia $dependencia, Seguimiento $seguimiento): string
    {
        return Workspace::getUrl([
            'workspace' => 'seguimiento-dependencia-reportar',
            'record' => $dependencia->id,
            'seguimiento' => $seguimiento->id,
        ]);
    }
}
