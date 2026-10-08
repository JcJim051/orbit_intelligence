<?php

namespace Tests\Feature\Intelligence;

use App\Enums\EstadoRevisionPasiva;
use App\Enums\OrigenCambioTecho;
use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\AuditLog;
use App\Models\Dependencia;
use App\Models\FuenteFinanciacion;
use App\Models\PasivaCarga;
use App\Models\PasivaLinea;
use App\Models\Proyecto;
use App\Models\Seguimiento;
use App\Models\Techo;
use App\Models\TechoHistorial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TechoFuenteCorreccionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_un_visitante_no_autenticado_vuelve_al_inicio_de_sesion(): void
    {
        [, $techo] = $this->escenario();

        $this->patch(route('intelligence.reporte-mensual.techos.update', $techo), $this->datos($techo->fuente_financiacion_id))
            ->assertRedirect(route('login'));

        $this->assertSame('1000.00', $techo->fresh()->valor);
    }

    public function test_administrador_y_enlace_corrigen_la_fuente_con_historial_y_auditoria(): void
    {
        [$seguimiento, $techo, $enlace, $admin, $fuente, $proyecto, $dependencia] = $this->escenario();
        $nueva = FuenteFinanciacion::factory()->create(['codigo' => '18', 'nombre' => 'Recursos del crédito']);

        $this->assertTrue($admin->can('crearFuente', [Techo::class, $seguimiento, $dependencia]));
        $this->assertTrue($enlace->can('corregirFuente', $techo));

        $this->actingAs($admin)
            ->post(route('intelligence.reporte-mensual.proyectos.techos.store', [$seguimiento, $proyecto, 'dependencia' => $dependencia->id]), $this->datos($nueva->id, [
                'asignado' => 700,
                'comprometido' => 200,
                'obligado' => 100,
                'pagado' => 50,
                'motivo' => 'La pasiva no trajo la fuente 18 del proyecto.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $creado = Techo::query()->where('fuente_financiacion_id', $nueva->id)->sole();
        $this->assertSame('0.00', $creado->valor_pasiva);
        $this->assertSame('700.00', $creado->valor_ajuste);
        $this->assertSame('700.00', $creado->valor);
        $this->assertSame('200.00', $creado->comprometido);
        $this->assertSame('100.00', $creado->obligado);
        $this->assertSame('50.00', $creado->pagado);
        $this->assertSame(0, $creado->lineas_count);

        $historial = $creado->historial()->first();
        $this->assertSame(OrigenCambioTecho::CorreccionFuente, $historial->origen);
        $this->assertSame($admin->id, $historial->user_id);
        $this->assertSame('La pasiva no trajo la fuente 18 del proyecto.', $historial->motivo);
        $this->assertSame('200.00', $historial->comprometido_nuevo);
        $this->assertTrue(AuditLog::query()->where('event', 'reporte_sectorial.techo_fuente_corregida')->where('actor_id', $admin->id)->exists());

        $this->actingAs($enlace)
            ->patch(route('intelligence.reporte-mensual.techos.update', $techo), $this->datos($fuente->id, [
                'asignado' => 1500,
                'comprometido' => 800,
                'obligado' => 600,
                'pagado' => 400,
                'motivo' => 'La definitiva de la fuente 20 estaba corrida.',
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $techo->refresh();
        $this->assertSame('1000.00', $techo->valor_pasiva);
        $this->assertSame('1500.00', $techo->valor);
        $this->assertSame('800.00', $techo->comprometido_ajuste);
        $this->assertSame('600.00', $techo->obligado);
        $this->assertSame('400.00', $techo->pagado);
        $this->assertTrue($techo->tieneAjuste());
        $this->assertSame($enlace->id, $techo->historial()->first()->user_id);
    }

    public function test_retirar_la_fuente_conserva_el_historial_y_se_puede_volver_a_crear(): void
    {
        [$seguimiento, $techo, $enlace, , $fuente, $proyecto, $dependencia] = $this->escenario();
        $carga = PasivaCarga::query()->create([
            'seguimiento_id' => $seguimiento->id,
            'disk' => 'local',
            'path' => 'reporte-sectorial/pasivas/prueba.xlsx',
            'nombre_original' => 'prueba.xlsx',
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'bytes' => 10,
            'sha256' => str_repeat('b', 64),
            'base_techo' => 'apropiacion_definitiva',
            'es_vigente' => true,
            'uploaded_by' => $enlace->id,
        ]);
        $linea = PasivaLinea::query()->create([
            'pasiva_carga_id' => $carga->id,
            'seguimiento_id' => $seguimiento->id,
            'fila' => 8,
            'identificacion_presupuestal' => '0301 - 2.3.45.4503.1000.001.2.3.2.02.02.008 - 20',
            'es_inversion' => true,
            'fuente_financiacion_id' => $fuente->id,
            'dependencia_id' => $dependencia->id,
            'proyecto_id' => $proyecto->id,
            'techo_id' => $techo->id,
            'apropiacion_definitiva' => 1000,
            'compromisos' => 400,
            'estado_revision' => EstadoRevisionPasiva::Asignada,
        ]);

        $this->actingAs($enlace)
            ->delete(route('intelligence.reporte-mensual.techos.destroy', $techo), [
                'motivo' => 'Esa fuente no corresponde a este proyecto.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($techo);
        $this->assertNull($linea->fresh()->techo_id);
        $retiro = TechoHistorial::query()->withoutGlobalScopes()->where('techo_id', $techo->id)->latest('id')->first();
        $this->assertSame(OrigenCambioTecho::CorreccionFuente, $retiro->origen);
        $this->assertSame('0.00', $retiro->valor_nuevo);
        $this->assertSame($enlace->id, $retiro->user_id);
        $this->assertTrue(AuditLog::query()->where('event', 'reporte_sectorial.techo_fuente_eliminada')->exists());

        $this->post(route('intelligence.reporte-mensual.proyectos.techos.store', [$seguimiento, $proyecto, 'dependencia' => $dependencia->id]), $this->datos($fuente->id, [
            'asignado' => 1100,
            'motivo' => 'La fuente sí corresponde y se devuelve al techo.',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $restaurado = Techo::query()->where('fuente_financiacion_id', $fuente->id)->sole();
        $this->assertSame($techo->id, $restaurado->id);
        $this->assertNull($restaurado->deleted_at);
        $this->assertSame('1000.00', $restaurado->valor_pasiva);
        $this->assertSame('1100.00', $restaurado->valor);
    }

    public function test_el_motivo_corto_y_la_fuente_repetida_se_rechazan(): void
    {
        [, $techo, $enlace, , $fuente] = $this->escenario();

        $this->actingAs($enlace)
            ->patch(route('intelligence.reporte-mensual.techos.update', $techo), $this->datos($fuente->id, ['motivo' => 'corto']))
            ->assertSessionHasErrors(['motivo' => 'El motivo debe tener al menos 10 caracteres.']);

        $this->assertSame('1000.00', $techo->fresh()->valor);

        $this->post(route('intelligence.reporte-mensual.proyectos.techos.store', [$techo->seguimiento, $techo->proyecto, 'dependencia' => $techo->dependencia_id]), $this->datos($fuente->id))
            ->assertSessionHasErrors(['fuente_financiacion_id' => 'Esa fuente ya tiene un techo en este proyecto y dependencia.']);
    }

    public function test_la_gerencia_no_corrige_fuentes_y_otro_sector_no_ve_el_techo(): void
    {
        [$seguimiento, $techo, , , $fuente, $proyecto] = $this->escenario();
        $gerencia = User::factory()->create(['role' => UserRole::Manager]);
        $ajeno = User::factory()->create(['role' => UserRole::Member]);
        $ajeno->dependencias()->attach(Dependencia::factory()->create());

        $this->assertFalse($gerencia->can('corregirFuente', $techo));

        $this->actingAs($gerencia)
            ->patch(route('intelligence.reporte-mensual.techos.update', $techo), $this->datos($fuente->id))
            ->assertForbidden();

        $this->actingAs($gerencia)
            ->get(Workspace::getUrl([
                'workspace' => 'seguimiento-proyecto-reportar',
                'record' => $seguimiento->getRouteKey().'-'.$proyecto->getRouteKey(),
            ]))
            ->assertOk()
            ->assertDontSee('Agregar fuente');

        $this->actingAs($ajeno)
            ->patch(route('intelligence.reporte-mensual.techos.update', $techo), $this->datos($fuente->id))
            ->assertNotFound();

        $this->assertSame('1000.00', $techo->fresh()->valor);
    }

    #[DataProvider('rolesSinCorreccionDeFuente')]
    public function test_los_demas_roles_no_pueden_corregir_la_fuente(UserRole $rol): void
    {
        [$seguimiento, $techo, , , , , $dependencia] = $this->escenario();
        $usuario = User::factory()->create(['role' => $rol]);
        $usuario->dependencias()->attach($dependencia);

        $this->assertFalse($usuario->can('corregirFuente', $techo));
        $this->assertFalse($usuario->can('crearFuente', [Techo::class, $seguimiento, $dependencia]));
    }

    public function test_un_seguimiento_cerrado_no_admite_la_correccion(): void
    {
        [$seguimiento, $techo, , $admin, $fuente] = $this->escenario();
        $gerencia = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($gerencia)
            ->post(route('intelligence.reporte-mensual.cerrar', $seguimiento))
            ->assertSessionHasNoErrors();

        $this->assertFalse($admin->can('corregirFuente', $techo->fresh()));

        $this->actingAs($admin)
            ->patch(route('intelligence.reporte-mensual.techos.update', $techo), $this->datos($fuente->id))
            ->assertForbidden();
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function rolesSinCorreccionDeFuente(): array
    {
        return [
            'gerente' => [UserRole::Manager],
            'gestor siid' => [UserRole::SiidManager],
            'revisor' => [UserRole::Reviewer],
            'apoyo de gerencia' => [UserRole::ManagementSupport],
            'revisor ods' => [UserRole::OdsReviewer],
            'validador ods' => [UserRole::OdsValidator],
        ];
    }

    /**
     * @return array{0: Seguimiento, 1: Techo, 2: User, 3: User, 4: FuenteFinanciacion, 5: Proyecto, 6: Dependencia}
     */
    private function escenario(): array
    {
        $dependencia = Dependencia::factory()->create(['codigo' => 'DAPD', 'nombre' => 'Departamento Administrativo de Planeación']);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $enlace = User::factory()->create(['role' => UserRole::Member, 'name' => 'Enlace de Planeación']);
        $enlace->dependencias()->attach($dependencia);
        $seguimiento = Seguimiento::factory()->create(['created_by' => $admin->id]);
        $proyecto = Proyecto::factory()->create(['bpin' => '2024005500085', 'nombre' => 'Proyecto de prueba']);
        $proyecto->dependencias()->attach($dependencia->id, ['es_responsable_principal' => true, 'origen' => 'pasiva']);
        $fuente = FuenteFinanciacion::factory()->create(['codigo' => '20', 'nombre' => 'Ingresos corrientes de libre destinación']);
        $respaldo = FuenteFinanciacion::factory()->create(['codigo' => '00AD', 'nombre' => 'Asignaciones directas']);
        $techo = Techo::query()->create([
            'seguimiento_id' => $seguimiento->id,
            'proyecto_id' => $proyecto->id,
            'fuente_financiacion_id' => $fuente->id,
            'dependencia_id' => $dependencia->id,
            'valor_pasiva' => 1000,
            'comprometido_pasiva' => 400,
            'obligado_pasiva' => 300,
            'pagado_pasiva' => 100,
            'base' => 'apropiacion_definitiva',
            'lineas_count' => 1,
        ]);
        Techo::query()->create([
            'seguimiento_id' => $seguimiento->id,
            'proyecto_id' => $proyecto->id,
            'fuente_financiacion_id' => $respaldo->id,
            'dependencia_id' => $dependencia->id,
            'valor_pasiva' => 50,
            'base' => 'apropiacion_definitiva',
            'lineas_count' => 0,
        ]);

        return [$seguimiento, $techo, $enlace, $admin, $fuente, $proyecto, $dependencia];
    }

    /**
     * @param  array<string, mixed>  $reemplazo
     * @return array<string, mixed>
     */
    private function datos(int $fuenteId, array $reemplazo = []): array
    {
        return array_merge([
            'fuente_financiacion_id' => $fuenteId,
            'asignado' => 1000,
            'comprometido' => 400,
            'obligado' => 300,
            'pagado' => 100,
            'motivo' => 'Corrección documentada de la fuente de financiación.',
        ], $reemplazo);
    }
}
