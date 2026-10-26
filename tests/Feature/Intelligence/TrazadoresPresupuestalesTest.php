<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Models\Actividad;
use App\Models\Dependencia;
use App\Models\EjecucionFinanciera;
use App\Models\FuenteFinanciacion;
use App\Models\PoliticaPublicaDepartamental;
use App\Models\Proyecto;
use App\Models\ProyectoTrazador;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\Trazador;
use App\Models\TrazadorCategoria;
use App\Models\User;
use App\Services\Intelligence\ReporteSectorial\AnaliticaTrazadores;
use App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial;
use Database\Seeders\TrazadoresPresupuestalesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class TrazadoresPresupuestalesTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_carga_los_catalogos_del_archivo_y_es_idempotente(): void
    {
        $this->seed(TrazadoresPresupuestalesSeeder::class);
        $this->seed(TrazadoresPresupuestalesSeeder::class);

        $this->assertSame(4, Trazador::query()->count());
        $this->assertSame(21, TrazadorCategoria::query()->count());
        $this->assertSame(16, PoliticaPublicaDepartamental::query()->count());
        $this->assertDatabaseHas('trazadores', [
            'codigo' => 'TD-001',
            'nombre' => 'Primera Infancia, infancia y adolescencia',
            'activo' => true,
        ]);
        $this->assertDatabaseHas('trazador_categorias', [
            'trazador_id' => Trazador::query()->where('codigo', 'TD-004')->value('id'),
            'nombre' => 'Pueblos Indígenas',
            'activo' => true,
        ]);
        $this->assertDatabaseHas('politicas_publicas_departamentales', [
            'nombre' => 'Política pública del deporte, recreación, actividad y educación física - Movernos en la Meta',
            'activo' => true,
        ]);
    }

    public function test_concilia_por_fuente_confirma_y_conserva_la_politica_del_corte(): void
    {
        [$reporte, $usuario, $asociacion, $categoria, $fuente] = $this->escenario();

        app(ServicioReporteSectorial::class)->guardarTrazadores($reporte, [[
            'proyecto_trazador_id' => $asociacion->id,
            'trazador_categoria_id' => $categoria->id,
            'fuente_financiacion_id' => $fuente->id,
            'asignado' => 600,
            'comprometido' => 500,
            'obligado' => 300,
            'pagado' => 200,
        ]], null, $usuario);

        $reporte->refresh();
        $this->assertNotNull($reporte->trazadores_confirmados_at);
        $this->assertSame($usuario->id, $reporte->trazadores_confirmados_por);
        $this->assertDatabaseHas('reporte_proyecto_trazador_distribuciones', [
            'reporte_proyecto_id' => $reporte->id,
            'politica_publica_departamental_id' => $asociacion->politica_publica_departamental_id,
            'asignado' => 600,
            'comprometido' => 500,
        ]);
        $politicaCorte = $asociacion->politica_publica_departamental_id;
        $politicaNueva = PoliticaPublicaDepartamental::query()->create(['nombre' => 'Política nueva', 'activo' => true]);
        $asociacion->update(['politica_publica_departamental_id' => $politicaNueva->id]);
        $this->assertDatabaseHas('reporte_proyecto_trazador_distribuciones', ['reporte_proyecto_id' => $reporte->id, 'politica_publica_departamental_id' => $politicaCorte]);

        $datos = app(AnaliticaTrazadores::class)->construir($reporte->seguimiento, [], null);
        $this->assertSame(['Sin trazador', $asociacion->trazador->etiqueta()], $datos['por_trazador']->pluck('nombre')->sort()->values()->all());
        $this->assertSame(1000.0, (float) $datos['por_trazador']->sum('asignado'));
        $this->assertSame(800.0, (float) $datos['por_trazador']->sum('comprometido'));
    }

    public function test_impide_superar_el_total_financiero_y_exige_la_jerarquia_de_valores(): void
    {
        [$reporte, $usuario, $asociacion, $categoria, $fuente] = $this->escenario();
        $this->expectException(ValidationException::class);

        app(ServicioReporteSectorial::class)->guardarTrazadores($reporte, [[
            'proyecto_trazador_id' => $asociacion->id,
            'trazador_categoria_id' => $categoria->id,
            'fuente_financiacion_id' => $fuente->id,
            'asignado' => 1100,
            'comprometido' => 900,
            'obligado' => 950,
            'pagado' => 0,
        ]], null, $usuario);
    }

    public function test_un_proyecto_con_trazador_queda_pendiente_hasta_confirmar_y_uno_sin_trazador_no(): void
    {
        [$reporte] = $this->escenario();
        $servicio = app(ServicioReporteSectorial::class);
        $this->assertTrue($servicio->pendientes($reporte)['trazadores']);

        $reporte->proyecto->trazadores()->update(['activo' => false]);
        $this->assertFalse($servicio->pendientes($reporte->fresh())['trazadores']);
    }

    public function test_una_disminucion_frente_al_corte_anterior_exige_justificacion(): void
    {
        [$reporte, $usuario, $asociacion, $categoria, $fuente] = $this->escenario();
        $anterior = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 8, 'fecha_corte' => '2026-08-31']);
        $reporteAnterior = ReporteProyecto::query()->create(['seguimiento_id' => $anterior->id, 'proyecto_id' => $reporte->proyecto_id, 'dependencia_id' => $reporte->dependencia_id]);
        $reporteAnterior->distribucionesTrazadores()->create([
            'proyecto_trazador_id' => $asociacion->id, 'trazador_categoria_id' => $categoria->id,
            'fuente_financiacion_id' => $fuente->id, 'trazador_id' => $asociacion->trazador_id,
            'politica_publica_departamental_id' => $asociacion->politica_publica_departamental_id,
            'asignado' => 700, 'comprometido' => 600, 'obligado' => 400, 'pagado' => 250,
        ]);

        try {
            app(ServicioReporteSectorial::class)->guardarTrazadores($reporte, [[
                'proyecto_trazador_id' => $asociacion->id, 'trazador_categoria_id' => $categoria->id,
                'fuente_financiacion_id' => $fuente->id, 'asignado' => 600, 'comprometido' => 500, 'obligado' => 300, 'pagado' => 200,
            ]], null, $usuario);
            $this->fail('Debió exigir justificación.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('justificacion_trazadores', $error->errors());
        }

        app(ServicioReporteSectorial::class)->guardarTrazadores($reporte, [[
            'proyecto_trazador_id' => $asociacion->id, 'trazador_categoria_id' => $categoria->id,
            'fuente_financiacion_id' => $fuente->id, 'asignado' => 600, 'comprometido' => 500, 'obligado' => 300, 'pagado' => 200,
        ]], 'Reclasificación autorizada para el corte.', $usuario);
        $this->assertSame('Reclasificación autorizada para el corte.', $reporte->fresh()->justificacion_trazadores);
    }

    public function test_la_importacion_es_idempotente_y_no_crea_bpines_desconocidos(): void
    {
        $proyecto = Proyecto::factory()->create(['bpin' => '2024005500001']);
        $path = storage_path('framework/testing/trazadores-prueba.xlsx');
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('TRAZADORES 09');
        $writer->addRow(Row::fromValues([null, null, 'N', 'Dependencia', 'BPIN', 'Objeto', 'Trazador', 'Política', 'Categoría']));
        $writer->addRow(Row::fromValues([null, null, 1, 'Dependencia', $proyecto->bpin, 'Objeto', 'Equidad de la Mujer', 'Política de equidad', 'Autonomía económica']));
        $writer->addRow(Row::fromValues([null, null, 2, 'Dependencia', '9999999999999', 'Objeto', 'Construcción de Paz', null, 'Reforma Rural Integral']));
        $writer->addNewSheetAndMakeItCurrent()->setName('Hoja1');
        $writer->addRow(Row::fromValues(['Política de equidad']));
        $writer->close();

        $this->artisan('trazadores:importar', ['archivo' => $path, '--apply' => true])->assertSuccessful();
        $this->artisan('trazadores:importar', ['archivo' => $path, '--apply' => true])->assertSuccessful();

        $this->assertSame(1, ProyectoTrazador::query()->count());
        $this->assertSame(1, TrazadorCategoria::query()->count());
        $this->assertSame(1, Trazador::query()->count());
        $this->assertSame(1, PoliticaPublicaDepartamental::query()->count());
        $this->assertSame(0, Proyecto::query()->where('bpin', '9999999999999')->count());
    }

    public function test_solo_administracion_y_revision_de_inversion_modifican_catalogos(): void
    {
        $sector = User::factory()->create(['role' => UserRole::Member]);
        $revisor = User::factory()->create(['role' => UserRole::InvestmentReviewer]);

        $this->actingAs($sector)->post(route('intelligence.trazadores.store'), ['nombre' => 'Trazador sectorial', 'activo' => 1])->assertForbidden();
        $this->actingAs($revisor)->post(route('intelligence.trazadores.store'), ['nombre' => 'Trazador autorizado', 'descripcion' => 'Creado por revisión', 'activo' => 1])->assertRedirect();

        $this->assertDatabaseHas('trazadores', ['codigo' => 'TD-001', 'nombre' => 'Trazador autorizado']);
    }

    /** @return array{ReporteProyecto, User, ProyectoTrazador, TrazadorCategoria, FuenteFinanciacion} */
    private function escenario(): array
    {
        $usuario = User::factory()->create(['role' => UserRole::Admin]);
        $dependencia = Dependencia::factory()->create();
        $proyecto = Proyecto::factory()->create();
        $proyecto->dependencias()->attach($dependencia);
        $seguimiento = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 9]);
        $fuente = FuenteFinanciacion::factory()->create();
        $actividad = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $dependencia->id]);
        $actividad->programaciones()->create(['fuente_financiacion_id' => $fuente->id, 'vigencia' => 2026, 'valor_asignado' => 1000]);
        $reporte = ReporteProyecto::query()->create(['seguimiento_id' => $seguimiento->id, 'proyecto_id' => $proyecto->id, 'dependencia_id' => $dependencia->id]);
        EjecucionFinanciera::query()->create(['reporte_proyecto_id' => $reporte->id, 'actividad_id' => $actividad->id, 'fuente_financiacion_id' => $fuente->id, 'comprometido' => 800, 'obligado' => 500, 'pagado' => 300]);
        $trazador = Trazador::query()->create(['codigo' => 'TD-001', 'nombre' => 'Equidad de la Mujer', 'activo' => true]);
        $categoria = TrazadorCategoria::query()->create(['trazador_id' => $trazador->id, 'nombre' => 'Autonomía económica', 'activo' => true]);
        $politica = PoliticaPublicaDepartamental::query()->create(['nombre' => 'Política de equidad de género', 'activo' => true]);
        $asociacion = ProyectoTrazador::query()->create(['proyecto_id' => $proyecto->id, 'trazador_id' => $trazador->id, 'politica_publica_departamental_id' => $politica->id, 'activo' => true]);
        $asociacion->categorias()->create(['trazador_categoria_id' => $categoria->id, 'activo' => true]);
        $reporte->setRelation('seguimiento', $seguimiento)->setRelation('proyecto', $proyecto)->setRelation('dependencia', $dependencia);

        return [$reporte, $usuario, $asociacion->setRelation('trazador', $trazador), $categoria, $fuente];
    }
}
