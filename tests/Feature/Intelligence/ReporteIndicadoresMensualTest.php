<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialDato;
use App\Models\Seguimiento;
use App\Models\User;
use App\Services\Intelligence\IndicadoresTerritoriales\CronogramaReporteIndicadores;
use App\Services\Intelligence\ReporteSectorial\ServicioReporteSectorial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReporteIndicadoresMensualTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_requests_previous_month_for_monthly_indicators(): void
    {
        $seguimiento = Seguimiento::factory()->make(['vigencia' => 2026, 'mes' => 10]);

        $periodo = app(CronogramaReporteIndicadores::class)->periodoExigible('mensual', $seguimiento);

        $this->assertSame('Septiembre 2026', $periodo['periodo']);
        $this->assertSame('2026-09-01', $periodo['periodo_inicio']->toDateString());
        $this->assertSame('2026-09-30', $periodo['periodo_fin']->toDateString());
        $this->assertTrue($periodo['obligatorio']);
    }

    public function test_annual_and_semester_reports_are_optional_first_and_mandatory_at_deadline(): void
    {
        $cronograma = app(CronogramaReporteIndicadores::class);

        $enero = Seguimiento::factory()->make(['vigencia' => 2026, 'mes' => 1]);
        $marzo = Seguimiento::factory()->make(['vigencia' => 2026, 'mes' => 3]);
        $julio = Seguimiento::factory()->make(['vigencia' => 2026, 'mes' => 7]);
        $agosto = Seguimiento::factory()->make(['vigencia' => 2026, 'mes' => 8]);

        $this->assertFalse($cronograma->periodoExigible('anual', $enero)['obligatorio']);
        $this->assertTrue($cronograma->periodoExigible('anual', $marzo)['obligatorio']);
        $this->assertSame('2025', $cronograma->periodoExigible('anual', $marzo)['periodo']);
        $this->assertFalse($cronograma->periodoExigible('semestral', $julio)['obligatorio']);
        $this->assertTrue($cronograma->periodoExigible('semestral', $agosto)['obligatorio']);
        $this->assertSame('01/01/2026 - 30/06/2026', $cronograma->periodoExigible('semestral', $agosto)['periodo']);
    }

    public function test_sector_reports_due_indicator_once_and_reviewer_approves_it(): void
    {
        $dependencia = Dependencia::factory()->create();
        $sector = User::factory()->create(['role' => UserRole::Member]);
        $sector->dependencias()->attach($dependencia);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $seguimiento = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 10]);
        $indicador = $this->indicator($dependencia, $reviewer, 'mensual');

        $this->actingAs($sector)->post(route('intelligence.reporte-mensual.indicadores.store', [
            $seguimiento, $dependencia, $indicador,
        ]), [
            'valor' => '42.5',
            'enlace_evidencia' => 'https://example.test/evidencia',
            'observacion' => 'Consolidado del mes.',
        ])->assertRedirect();

        $dato = IndicadorTerritorialDato::query()->where('indicador_territorial_id', $indicador->id)->firstOrFail();
        $this->assertSame('Septiembre 2026', $dato->periodo);
        $this->assertSame('reporte_mensual', $dato->origen);
        $this->assertSame('pendiente', $dato->estado);
        $this->assertSame($seguimiento->id, $dato->seguimiento_id);

        $this->actingAs($sector)->post(route('intelligence.reporte-mensual.indicadores.store', [
            $seguimiento, $dependencia, $indicador,
        ]), ['valor' => 99])->assertSessionHasErrors('indicador');

        $this->actingAs($reviewer)->patch(route('intelligence.reporte-mensual.indicadores.review', $dato), [
            'decision' => 'aprobar',
        ])->assertRedirect();

        $this->assertDatabaseHas('indicador_territorial_datos', [
            'id' => $dato->id,
            'estado' => 'aprobado',
            'validado_por' => $reviewer->id,
        ]);

        $this->actingAs($manager)
            ->get(\App\Filament\Pages\Workspace::getUrl([
                'workspace' => 'seguimiento-dependencia-reportar',
                'record' => $dependencia->id,
                'seguimiento' => $seguimiento->id,
            ]))
            ->assertOk()
            ->assertSee('Ver reporte')
            ->assertSee('Vista del reporte enviado')
            ->assertSee('Modo consulta · este reporte no será modificado')
            ->assertSee('Consolidado del mes.')
            ->assertSee('Abrir evidencia');
    }

    public function test_report_manager_can_submit_an_indicator_without_direct_dependency_membership(): void
    {
        $dependencia = Dependencia::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $seguimiento = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 10]);
        $indicador = $this->indicator($dependencia, $reviewer, 'mensual');

        $this->actingAs($manager)->post(route('intelligence.reporte-mensual.indicadores.store', [
            $seguimiento, $dependencia, $indicador,
        ]), [
            'valor' => 27,
            'observacion' => 'Reporte registrado durante la mesa sectorial.',
        ])->assertRedirect();

        $this->assertDatabaseHas('indicador_territorial_datos', [
            'indicador_territorial_id' => $indicador->id,
            'seguimiento_id' => $seguimiento->id,
            'reportado_por' => $manager->id,
            'observacion' => 'Reporte registrado durante la mesa sectorial.',
        ]);

        $this->actingAs($manager)
            ->get(\App\Filament\Pages\Workspace::getUrl([
                'workspace' => 'indicador-territorial',
                'record' => $indicador->id,
            ]))
            ->assertOk()
            ->assertSee('Revisar reportes pendientes (1)')
            ->assertSee('Validación del reporte')
            ->assertSee('Reporte registrado durante la mesa sectorial.')
            ->assertSee('Aprobar reporte')
            ->assertDontSee('Aprobar cambios (1)');
    }

    public function test_observed_report_is_returned_to_the_sector_for_correction_and_resubmission(): void
    {
        $dependencia = Dependencia::factory()->create();
        $sector = User::factory()->create(['role' => UserRole::Member]);
        $sector->dependencias()->attach($dependencia);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $seguimiento = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 10]);
        $indicador = $this->indicator($dependencia, $reviewer, 'mensual');

        $this->actingAs($sector)->post(route('intelligence.reporte-mensual.indicadores.store', [
            $seguimiento, $dependencia, $indicador,
        ]), [
            'valor' => 12,
            'observacion' => 'Primer consolidado.',
        ])->assertRedirect();

        $dato = $indicador->datos()->firstOrFail();
        $this->actingAs($reviewer)->patch(route('intelligence.reporte-mensual.indicadores.review', $dato), [
            'decision' => 'observar',
            'observacion_revision' => 'Verifique el total con el registro municipal.',
        ])->assertRedirect();

        $this->actingAs($manager)
            ->get(\App\Filament\Pages\Workspace::getUrl([
                'workspace' => 'seguimiento-dependencia-reportar',
                'record' => $dependencia->id,
                'seguimiento' => $seguimiento->id,
            ]))
            ->assertOk()
            ->assertSee('Corregir')
            ->assertSee('Observación del enlace de información')
            ->assertSee('Verifique el total con el registro municipal.')
            ->assertSee('Enviar corrección');

        $this->actingAs($sector)->post(route('intelligence.reporte-mensual.indicadores.store', [
            $seguimiento, $dependencia, $indicador,
        ]), [
            'valor' => 18,
            'enlace_evidencia' => 'https://example.test/correccion',
            'observacion' => 'Total corregido con el registro municipal.',
        ])->assertRedirect()->assertSessionHas('status', 'Corrección enviada. El reporte volvió a quedar pendiente de validación.');

        $dato->refresh();
        $this->assertSame(1, $indicador->datos()->count());
        $this->assertSame('18.000000', $dato->valor);
        $this->assertSame('pendiente', $dato->estado);
        $this->assertNull($dato->validado_por);
        $this->assertSame('Verifique el total con el registro municipal.', $dato->observacion_revision);
        $this->assertSame('Total corregido con el registro municipal.', $dato->observacion);

        $this->actingAs($reviewer)->patch(route('intelligence.reporte-mensual.indicadores.review', $dato), [
            'decision' => 'aprobar',
        ])->assertRedirect();

        $this->assertSame('aprobado', $dato->fresh()->estado);
    }

    public function test_previous_optional_report_satisfies_march_deadline(): void
    {
        $dependencia = Dependencia::factory()->create();
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicador = $this->indicator($dependencia, $reviewer, 'anual');
        $enero = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 1]);
        $marzo = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 3]);
        IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicador->id,
            'seguimiento_id' => $enero->id,
            'periodo' => '2025',
            'valor' => 80,
            'estado' => 'pendiente',
            'origen' => 'reporte_mensual',
        ]);

        $pendientes = app(CronogramaReporteIndicadores::class)->pendientesObligatorios($marzo, $dependencia->id);

        $this->assertCount(0, $pendientes);
    }

    public function test_assigned_reviewer_can_approve_all_pending_periodic_reports_for_one_indicator(): void
    {
        $dependencia = Dependencia::factory()->create();
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicador = $this->indicator($dependencia, $reviewer, 'mensual');
        foreach (['Agosto 2026', 'Septiembre 2026'] as $periodo) {
            IndicadorTerritorialDato::query()->create([
                'indicador_territorial_id' => $indicador->id,
                'periodo' => $periodo,
                'valor' => 10,
                'estado' => 'pendiente',
                'origen' => 'reporte_mensual',
            ]);
        }

        $this->actingAs($reviewer)
            ->patch(route('intelligence.reporte-mensual.indicadores.approve-pending', $indicador))
            ->assertRedirect();

        $this->assertSame(2, $indicador->datos()->where('estado', 'aprobado')->count());
        $this->assertSame(0, $indicador->datos()->where('estado', 'pendiente')->count());
    }

    public function test_monthly_cut_cannot_close_with_missing_or_unapproved_indicator_reports(): void
    {
        $dependencia = Dependencia::factory()->create();
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicador = $this->indicator($dependencia, $reviewer, 'mensual');
        $seguimiento = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 10]);
        $cronograma = app(CronogramaReporteIndicadores::class);

        $this->assertCount(1, $cronograma->pendientesParaCerrar($seguimiento));
        try {
            app(ServicioReporteSectorial::class)->cerrar($seguimiento, User::factory()->create(['role' => UserRole::Manager]));
            $this->fail('El corte no debió cerrarse con el indicador obligatorio pendiente.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('indicadores', $exception->errors());
        }

        $dato = IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicador->id,
            'seguimiento_id' => $seguimiento->id,
            'periodo' => 'Septiembre 2026',
            'periodo_inicio' => '2026-09-01',
            'periodo_fin' => '2026-09-30',
            'valor' => 15,
            'estado' => 'pendiente',
            'origen' => 'reporte_mensual',
        ]);

        $this->assertStringContainsString('Pendiente de validación', $cronograma->pendientesParaCerrar($seguimiento)->first());

        $dato->update(['estado' => 'aprobado', 'validado_por' => $reviewer->id, 'validado_at' => now()]);

        $this->assertCount(0, $cronograma->pendientesParaCerrar($seguimiento));
    }

    private function indicator(Dependencia $dependencia, User $reviewer, string $periodicidad): IndicadorTerritorial
    {
        return IndicadorTerritorial::query()->create([
            'codigo' => 'IT-'.fake()->unique()->numerify('####'),
            'nombre' => 'Indicador periódico de prueba',
            'dependencia_id' => $dependencia->id,
            'dependencia_fuente' => $dependencia->nombre,
            'unidad_medida' => 'Número',
            'periodicidad' => $periodicidad,
            'estado' => 'historico_validado',
            'grupo_asignacion' => 'sin_asignar',
            'asignado_a' => $reviewer->id,
        ]);
    }
}
