<?php

namespace Tests\Feature\Intelligence;

use App\Enums\EstadoReporteProyecto;
use App\Enums\EstadoRevisionPasiva;
use App\Enums\OrigenCambioTecho;
use App\Enums\TipoFocalizacion;
use App\Enums\TipoReglaPasiva;
use App\Enums\UserRole;
use App\Exceptions\CorteCerradoException;
use App\Filament\Pages\Workspace;
use App\Models\Actividad;
use App\Models\AvanceFisico;
use App\Models\Dependencia;
use App\Models\DependenciaReglaPasiva;
use App\Models\EjecucionFinanciera;
use App\Models\Evidencia;
use App\Models\FuenteFinanciacion;
use App\Models\MetaProducto;
use App\Models\Municipio;
use App\Models\PasivaCarga;
use App\Models\PasivaLinea;
use App\Models\Proyecto;
use App\Models\ReporteProyecto;
use App\Models\Seguimiento;
use App\Models\SeguimientoCargaHistorica;
use App\Models\Techo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class ReporteSectorialTest extends TestCase
{
    use RefreshDatabase;

    private const BPIN_PLANEACION = '2024005500096';

    private const BPIN_AGRICULTURA = '2024005500068';

    private const BPIN_AGRICULTURA_POR_REGLA_BPIN = '2025005500024';

    private Dependencia $planeacion;

    private Dependencia $agricultura;

    private FuenteFinanciacion $propios;

    private FuenteFinanciacion $sgr;

    private User $admin;

    private User $gerencia;

    private User $sectorPlaneacion;

    private User $sectorAgricultura;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('local');

        $this->planeacion = Dependencia::factory()->create(['codigo' => 'DAPD', 'nombre' => 'Departamento Administrativo de Planeación']);
        $this->agricultura = Dependencia::factory()->create(['codigo' => 'SAGR', 'nombre' => 'Secretaría de Agricultura']);

        DependenciaReglaPasiva::factory()->create(['dependencia_id' => $this->planeacion->id, 'tipo_regla' => TipoReglaPasiva::UnidadPct, 'valor' => '0301', 'prioridad' => null]);
        DependenciaReglaPasiva::factory()->create(['dependencia_id' => $this->agricultura->id, 'tipo_regla' => TipoReglaPasiva::SectorMga, 'valor' => '17', 'prioridad' => null]);
        DependenciaReglaPasiva::factory()->create(['dependencia_id' => $this->agricultura->id, 'tipo_regla' => TipoReglaPasiva::Bpin, 'valor' => self::BPIN_AGRICULTURA_POR_REGLA_BPIN, 'prioridad' => null]);

        $this->propios = FuenteFinanciacion::factory()->create(['codigo' => '20', 'nombre' => 'Ingresos corrientes de libre destinación']);
        $this->sgr = FuenteFinanciacion::factory()->sgr()->create(['codigo' => '00AD', 'nombre' => 'Asignaciones Directas 20%']);

        $this->admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->gerencia = User::factory()->create(['role' => UserRole::Manager]);
        $this->sectorPlaneacion = User::factory()->create(['role' => UserRole::Member]);
        $this->sectorPlaneacion->dependencias()->attach($this->planeacion);
        $this->sectorAgricultura = User::factory()->create(['role' => UserRole::Member]);
        $this->sectorAgricultura->dependencias()->attach($this->agricultura);
    }

    public function test_la_pasiva_deriva_techos_por_proyecto_fuente_y_dependencia_con_las_reglas(): void
    {
        $seguimiento = $this->seguimientoConPasiva();

        $carga = PasivaCarga::query()->sole();
        $this->assertTrue($carga->es_vigente);
        $this->assertSame(9, $carga->lineas_total);
        $this->assertSame(8, $carga->lineas_inversion);
        $this->assertSame(3, $carga->lineas_pendientes);
        $this->assertSame(hash_file('sha256', $this->rutaFixture()), $carga->sha256);
        Storage::disk('local')->assertExists($carga->path);

        $this->assertTecho($seguimiento, self::BPIN_PLANEACION, $this->propios, $this->planeacion, 308000000);
        $this->assertTecho($seguimiento, self::BPIN_PLANEACION, $this->sgr, $this->planeacion, 50000000);
        $this->assertTecho($seguimiento, self::BPIN_AGRICULTURA, $this->propios, $this->agricultura, 60000000);
        $this->assertTecho($seguimiento, self::BPIN_AGRICULTURA_POR_REGLA_BPIN, $this->sgr, $this->agricultura, 120000000);
        $this->assertSame(4, Techo::query()->count());

        $motivos = PasivaLinea::query()->where('estado_revision', EstadoRevisionPasiva::Pendiente)->pluck('motivos_revision')->flatten()->sort()->values()->all();
        $this->assertSame(['fuente_no_catalogada', 'sin_bpin', 'sin_dependencia'], $motivos);
        $this->assertSame(1, PasivaLinea::query()->where('estado_revision', EstadoRevisionPasiva::NoAplica)->count());

        $proyecto = Proyecto::query()->where('bpin', self::BPIN_PLANEACION)->sole();
        $this->assertStringStartsWith('FORTALECIMIENTO A LA PLANEACIÓN', $proyecto->nombre);
        $this->assertTrue((bool) $proyecto->dependencias()->sole()->pivot->es_responsable_principal);
        $this->assertSame(['carga_pasiva'], Techo::query()->first()->historial->pluck('origen')->map->value->all());
    }

    public function test_recargar_la_pasiva_recalcula_los_techos_y_deja_historial(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $csv = str_replace('0301 - 2.3.04.0401.1003.001.2.3.2.02.02.008 - 20;Servicios prestados a las empresas y servicios de producción;300000000;0;300000000', '0301 - 2.3.04.0401.1003.001.2.3.2.02.02.008 - 20;Servicios prestados a las empresas y servicios de producción;300000000;-100000000;200000000', (string) file_get_contents($this->rutaFixture()));

        $this->actingAs($this->admin)
            ->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => UploadedFile::fake()->createWithContent('pasiva_v2.csv', $csv)])
            ->assertRedirect();

        $techo = $this->assertTecho($seguimiento, self::BPIN_PLANEACION, $this->propios, $this->planeacion, 208000000);
        $this->assertSame(1, PasivaCarga::query()->where('es_vigente', true)->count());
        $this->assertSame(2, PasivaCarga::query()->count());
        $this->assertSame(['208000000.00', '308000000.00'], $techo->historial()->pluck('valor_nuevo')->all());
    }

    public function test_gerencia_puede_editar_el_mes_de_un_seguimiento_abierto(): void
    {
        $this->actingAs($this->gerencia)
            ->post(route('intelligence.reporte-mensual.store'), ['vigencia' => 2026, 'mes' => 9, 'observacion' => 'Creado como septiembre'])
            ->assertRedirect();

        $seguimiento = Seguimiento::query()->where(['vigencia' => 2026, 'mes' => 9])->sole();

        $this->followingRedirects()
            ->get(route('intelligence.reporte-mensual.edit', $seguimiento))
            ->assertOk()
            ->assertSee('Editar seguimiento');

        $this->patch(route('intelligence.reporte-mensual.update', $seguimiento), [
            'vigencia' => 2026,
            'mes' => 8,
            'observacion' => 'Corrección a agosto',
        ])->assertRedirect(route('intelligence.reporte-mensual.show', $seguimiento));

        $seguimiento->refresh();
        $this->assertSame(8, $seguimiento->mes);
        $this->assertSame('Agosto 2026', $seguimiento->etiqueta());
        $this->assertSame('2026-08-31', $seguimiento->fecha_corte->toDateString());
        $this->assertSame('Corrección a agosto', $seguimiento->observacion);
    }

    public function test_el_sector_solo_ve_sus_bpin_con_los_techos_por_grupo_de_fuente(): void
    {
        $seguimiento = $this->seguimientoConPasiva();

        $this->actingAs($this->sectorPlaneacion)
            ->followingRedirects()
            ->get(route('intelligence.reporte-mensual.datos-base', $seguimiento))
            ->assertOk()
            ->assertSee(self::BPIN_PLANEACION.' — FORTALECIMIENTO A LA PLANEACIÓN', false)
            ->assertSeeInOrder(['Recursos propios', 'SGR'])
            ->assertSeeInOrder(['$308.000.000', '$50.000.000'])
            ->assertDontSee(self::BPIN_AGRICULTURA)
            ->assertDontSee(self::BPIN_AGRICULTURA_POR_REGLA_BPIN)
            ->assertDontSee('Cargar pasiva');

        $this->actingAs($this->sectorAgricultura)
            ->followingRedirects()
            ->get(route('intelligence.reporte-mensual.datos-base', $seguimiento))
            ->assertOk()
            ->assertSee(self::BPIN_AGRICULTURA.' — FORTALECIMIENTO A LOS MODELOS ASOCIATIVOS', false)
            ->assertSee(self::BPIN_AGRICULTURA_POR_REGLA_BPIN.' — ESTUDIOS DE PREINVERSIÓN', false)
            ->assertSee('$120.000.000')
            ->assertDontSee(self::BPIN_PLANEACION);
    }

    public function test_gerencia_importa_avance_historico_validado_por_meta_sin_exigir_evidencia(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_PLANEACION);
        $meta = MetaProducto::factory()->create([
            'codigo' => '31011014003',
            'nombre' => 'Asignar subsidios de vivienda',
            'dependencia_id' => $this->planeacion->id,
        ]);
        $meta->proyectos()->attach($proyecto);

        $encabezados = $this->encabezadosMetas();
        $encabezados[18] = '';

        $archivo = $this->xlsxMetas([
            ['Información de seguimiento', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
            $encabezados,
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Asignar subsidios de vivienda', 'Departamento Administrativo de Planeación', '50%', 10, 4, 'NO PROGRAM', 90000000, 80000000, 70000000, 'Validado por la gerencia'],
        ]);

        $this->actingAs($this->admin)
            ->post(route('intelligence.reporte-mensual.historicas.diagnosticar', $seguimiento), ['archivo' => $archivo])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $carga = SeguimientoCargaHistorica::query()->sole();
        $this->assertSame(1, $carga->filas_validas);
        $this->assertSame(0, $carga->filas_bloqueadas);

        $this->post(route('intelligence.reporte-mensual.historicas.importar', [$seguimiento, $carga]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $reporte = ReporteProyecto::query()->withoutGlobalScopes()->sole();
        $this->assertSame(EstadoReporteProyecto::Reportado, $reporte->estado);

        $actividad = Actividad::query()->withoutGlobalScopes()->sole();
        $this->assertSame('HIST-2026-08-31011014003', $actividad->codigo);
        $this->assertSame(Actividad::ORIGEN_HISTORICO, $actividad->origen);
        $this->assertSame('10.0000', $actividad->cantidad_programada);
        $this->assertSame(Seguimiento::MODO_HISTORICO, $seguimiento->fresh()->modo_captura);

        $avance = AvanceFisico::query()->withoutGlobalScopes()->sole();
        $this->assertSame('4.0000', $avance->cantidad);
        $this->assertStringContainsString('Carga histórica validada sin evidencia', (string) $avance->descripcion);
        $this->assertSame(0, Evidencia::query()->withoutGlobalScopes()->count());

        $fuenteHistorica = FuenteFinanciacion::query()->where('codigo', 'HIST_EXT')->sole();
        $this->assertSame('Histórico consolidado seguimiento externo', $fuenteHistorica->nombre);
        $this->assertTecho($seguimiento, self::BPIN_PLANEACION, $fuenteHistorica, $this->planeacion, 90000000);

        $ejecucion = EjecucionFinanciera::query()->withoutGlobalScopes()->sole();
        $this->assertSame('80000000.00', $ejecucion->comprometido);
        $this->assertSame('70000000.00', $ejecucion->obligado);
        $this->assertSame('70000000.00', $ejecucion->pagado);

        $this->post(route('intelligence.reporte-mensual.historicas.importar', [$seguimiento, $carga]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Actividad::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, AvanceFisico::query()->withoutGlobalScopes()->count());
        $this->assertSame(1, EjecucionFinanciera::query()->withoutGlobalScopes()->count());

        $this->get(route('intelligence.reporte-mensual.analitica.index', $seguimiento))
            ->assertRedirect();

        $this->followingRedirects()
            ->get(route('intelligence.reporte-mensual.analitica.index', $seguimiento))
            ->assertOk()
            ->assertSee('Metas producto')
            ->assertSee('31011014003')
            ->assertSee('Asignar subsidios de vivienda')
            ->assertSee('$80.000.000');

        $csv = $this->get(route('intelligence.reporte-mensual.analitica.download', $seguimiento))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString('meta_producto_codigo', $csv);
        $this->assertStringContainsString('31011014003', $csv);
    }

    public function test_la_carga_historica_permita_descargar_plantilla_prediligenciada(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_PLANEACION);
        $meta = MetaProducto::factory()->create([
            'codigo' => '31011014003',
            'nombre' => 'Asignar subsidios de vivienda',
            'dependencia_id' => $this->planeacion->id,
        ]);
        $meta->proyectos()->attach($proyecto);
        Actividad::factory()->create([
            'proyecto_id' => $proyecto->id,
            'dependencia_id' => $this->planeacion->id,
            'meta_producto_id' => $meta->id,
            'codigo' => 'META-31011014003',
            'cantidad_programada' => 10,
            'origen' => Actividad::ORIGEN_CONSOLIDADO_META,
        ]);

        $this->actingAs($this->admin)
            ->get(route('intelligence.reporte-mensual.historicas.plantilla', $seguimiento))
            ->assertOk()
            ->assertDownload('plantilla-avance-consolidado-2026-08.xlsx');

        $this->followingRedirects()
            ->get(route('intelligence.reporte-mensual.historicas.index', $seguimiento))
            ->assertOk()
            ->assertSee('Descargar plantilla prediligenciada')
            ->assertSee(route('intelligence.reporte-mensual.historicas.plantilla', $seguimiento), false);
    }

    public function test_la_carga_historica_bloquea_codigos_invalidos_duplicados_y_metas_con_varios_bpin(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $metaDuplicada = MetaProducto::factory()->create([
            'codigo' => '31011014003',
            'dependencia_id' => $this->planeacion->id,
        ]);
        $metaDuplicada->proyectos()->attach($this->proyecto(self::BPIN_PLANEACION));
        $metaMultiple = MetaProducto::factory()->create([
            'codigo' => '31011014004',
            'dependencia_id' => $this->planeacion->id,
        ]);
        $metaMultiple->proyectos()->attach([$this->proyecto(self::BPIN_PLANEACION)->id, $this->proyecto(self::BPIN_AGRICULTURA)->id]);

        $archivo = $this->xlsxMetas([
            ['Agrupador'],
            $this->encabezadosMetas(),
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '-', 'Sin código', 'Departamento Administrativo de Planeación', 0, 1, 0, 0, 0, 0, 0, null],
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Meta duplicada A', 'Departamento Administrativo de Planeación', 0, 1, 0, 0, 0, 0, 0, null],
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Meta duplicada B', 'Departamento Administrativo de Planeación', 0, 1, 0, 0, 0, 0, 0, null],
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014004', 'Meta con varios BPIN', 'Departamento Administrativo de Planeación', 0, 1, 0, 0, 0, 0, 0, null],
        ]);

        $this->actingAs($this->admin)
            ->post(route('intelligence.reporte-mensual.historicas.diagnosticar', $seguimiento), ['archivo' => $archivo])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $carga = SeguimientoCargaHistorica::query()->sole();
        $this->assertSame(0, $carga->filas_validas);
        $this->assertSame(4, $carga->filas_bloqueadas);

        $this->post(route('intelligence.reporte-mensual.historicas.importar', [$seguimiento, $carga]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Actividad::query()->withoutGlobalScopes()->count());
        $csv = $this->get(route('intelligence.reporte-mensual.historicas.errores', [$seguimiento, $carga]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Código de meta producto vacío, inválido o textual', $csv);
        $this->assertStringContainsString('Código de meta producto repetido', $csv);
        $this->assertStringContainsString('más de un BPIN', $csv);
    }

    public function test_la_carga_historica_acepta_matriz_de_proyectos_para_resolver_bpin_por_meta(): void
    {
        $this->actingAs($this->gerencia)
            ->post(route('intelligence.reporte-mensual.store'), ['vigencia' => 2026, 'mes' => 8])
            ->assertRedirect();
        $seguimiento = Seguimiento::query()->where(['vigencia' => 2026, 'mes' => 8])->sole();
        $meta = MetaProducto::factory()->create([
            'codigo' => '31011014003',
            'nombre' => 'Asignar subsidios de vivienda',
            'dependencia_id' => $this->planeacion->id,
        ]);

        $archivoMetas = $this->xlsxMetas([
            ['Agrupador'],
            $this->encabezadosMetas(),
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Asignar subsidios de vivienda', 'Departamento Administrativo de Planeación', 0, 10, 4, 40, 150000, 110000, 90000, 'Consolidado de la meta'],
        ]);
        $archivoProyectos = $this->xlsxMetas([
            ['Agrupador', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVANCES FINANCIEROS'],
            $this->encabezadosProyectos(),
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Asignar subsidios de vivienda', 0, 'Hogares beneficiados', 'Suma', '', '', '', 'Departamento Administrativo de Planeación', '2026005500001', 'Proyecto de vivienda uno', '', '191 RB Recursos del Balance Regalías por Petroleo Libre', 100000, 80000, 70000, 80, 10, 4, 40, 150000, 110000, 90000, 'Primer BPIN'],
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Asignar subsidios de vivienda', 0, 'Hogares beneficiados', 'Suma', '', '', '', 'Departamento Administrativo de Planeación', '2026005500002', 'Proyecto de vivienda dos', '', '00AD - SGR', 50000, 30000, 20000, '', '', '', '', '', '', '', 'Segundo BPIN'],
        ], 'proyectos_agosto.xlsx');

        $this->actingAs($this->admin)->post(route('intelligence.reporte-mensual.historicas.diagnosticar', $seguimiento), [
            'archivo' => $archivoMetas,
            'archivo_proyectos' => $archivoProyectos,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $carga = SeguimientoCargaHistorica::query()->sole();
        $this->assertSame(2, $carga->filas_validas);
        $this->assertSame(0, $carga->filas_bloqueadas);

        $this->post(route('intelligence.reporte-mensual.historicas.importar', [$seguimiento, $carga]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Proyecto::query()->withoutGlobalScopes()->count());
        $this->assertSame(2, ReporteProyecto::query()->withoutGlobalScopes()->count());
        $this->assertSame(2, Actividad::query()->withoutGlobalScopes()->count());
        $this->assertSame(2, EjecucionFinanciera::query()->withoutGlobalScopes()->count());
        $this->assertSame(2, $meta->fresh()->proyectos()->withoutGlobalScopes()->count());
        $this->assertDatabaseHas('fuentes_financiacion', [
            'codigo' => '191 RB',
            'nombre' => 'Recursos del Balance Regalías por Petroleo Libre',
        ]);
    }

    public function test_la_carga_historica_bloquea_valores_financieros_fuera_de_rango(): void
    {
        $this->actingAs($this->gerencia)
            ->post(route('intelligence.reporte-mensual.store'), ['vigencia' => 2026, 'mes' => 8])
            ->assertRedirect();
        $seguimiento = Seguimiento::query()->where(['vigencia' => 2026, 'mes' => 8])->sole();
        MetaProducto::factory()->create([
            'codigo' => '31011014003',
            'dependencia_id' => $this->planeacion->id,
        ]);

        $archivoMetas = $this->xlsxMetas([
            ['Agrupador'],
            $this->encabezadosMetas(),
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Asignar subsidios de vivienda', 'Departamento Administrativo de Planeación', 0, 10, 4, 40, 0, 0, 0, null],
        ]);
        $archivoProyectos = $this->xlsxMetas([
            ['Agrupador'],
            $this->encabezadosProyectos(),
            ['Pilar', 'Eje', 'Línea', 'Programa', 'Subprograma', 'Sector', 'Meta resultado', 'Indicador', '31011014003', 'Asignar subsidios de vivienda', 0, 'Hogares beneficiados', 'Suma', '', '', '', 'Departamento Administrativo de Planeación', '2026005500099', 'Proyecto con valor errado', '', '191 RB Recursos del Balance Regalías por Petroleo Libre', '1.4300000012E+25', 0, 0, 0, 10, 4, 40, 0, 0, 0, null],
        ], 'proyectos_agosto.xlsx');

        $this->actingAs($this->admin)->post(route('intelligence.reporte-mensual.historicas.diagnosticar', $seguimiento), [
            'archivo' => $archivoMetas,
            'archivo_proyectos' => $archivoProyectos,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $carga = SeguimientoCargaHistorica::query()->sole();
        $this->assertSame(0, $carga->filas_validas);
        $this->assertSame(1, $carga->filas_bloqueadas);
        $this->assertStringContainsString('valor fuera del rango permitido', json_encode($carga->diagnostico, JSON_UNESCAPED_UNICODE));
    }

    public function test_administracion_y_gerencia_ven_todos_los_sectores(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        foreach ([$admin, $this->gerencia] as $usuario) {
            $this->actingAs($usuario)
                ->followingRedirects()
                ->get(route('intelligence.reporte-mensual.datos-base', $seguimiento))
                ->assertOk()
                ->assertSee(self::BPIN_PLANEACION)
                ->assertSee(self::BPIN_AGRICULTURA)
                ->assertSee(self::BPIN_AGRICULTURA_POR_REGLA_BPIN);
        }

        $this->actingAs($admin)->get(route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $this->proyecto(self::BPIN_AGRICULTURA)]))->assertOk();
    }

    public function test_la_portada_del_seguimiento_filtra_la_tabla_operativa_por_dependencia(): void
    {
        $seguimiento = $this->seguimientoConPasiva();

        $this->actingAs($this->gerencia)
            ->get(Workspace::getUrl([
                'workspace' => 'seguimiento',
                'record' => $seguimiento->getRouteKey(),
                'dependencia' => $this->agricultura->id,
            ]))
            ->assertOk()
            ->assertSee('Proyectos para reportar')
            ->assertSee(self::BPIN_AGRICULTURA)
            ->assertSee(self::BPIN_AGRICULTURA_POR_REGLA_BPIN)
            ->assertDontSee(self::BPIN_PLANEACION);
    }

    public function test_el_detalle_del_proyecto_muestra_totales_en_las_tablas_de_reporte(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_PLANEACION);
        $actividad = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $this->planeacion->id]);
        $actividad->programaciones()->create([
            'fuente_financiacion_id' => $this->propios->id,
            'vigencia' => $seguimiento->vigencia,
            'valor_asignado' => 100000,
        ]);

        $this->actingAs($this->sectorPlaneacion)
            ->get(route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $proyecto, 'dependencia' => $this->planeacion->id]))
            ->assertOk()
            ->assertSee('Total proyecto')
            ->assertSee('$358.000.000')
            ->assertSee('Programado distribuido')
            ->assertSee('Reportar meta física')
            ->assertSee('techo sin distribuir');
    }

    public function test_gerencia_puede_reportar_avance_en_cualquier_sector_visible(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_AGRICULTURA);
        $actividad = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $this->agricultura->id]);

        $this->actingAs($this->gerencia)
            ->put(route('intelligence.reporte-mensual.proyectos.ejecucion.update', [$seguimiento, $proyecto, 'dependencia' => $this->agricultura->id]), [
                'ejecucion' => [[
                    'actividad_id' => $actividad->id,
                    'fuente_financiacion_id' => $this->propios->id,
                    'comprometido' => 1000,
                    'obligado' => 500,
                    'pagado' => 250,
                ]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1000.0, (float) EjecucionFinanciera::query()->withoutGlobalScopes()->sum('comprometido'));
    }

    public function test_un_bpin_de_otro_sector_responde_404_aunque_se_fuerce_el_id(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $ajeno = $this->proyecto(self::BPIN_AGRICULTURA);
        $actividadAjena = Actividad::factory()->create(['proyecto_id' => $ajeno->id, 'dependencia_id' => $this->agricultura->id]);

        $this->actingAs($this->sectorPlaneacion);
        $this->get(route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $ajeno]))->assertNotFound();
        $this->get(route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $ajeno, 'dependencia' => $this->agricultura->id]))->assertNotFound();
        $this->put(route('intelligence.reporte-mensual.proyectos.ejecucion.update', [$seguimiento, $ajeno]), ['ejecucion' => [['actividad_id' => $actividadAjena->id, 'fuente_financiacion_id' => $this->propios->id, 'comprometido' => 1, 'obligado' => 0, 'pagado' => 0]]])->assertNotFound();
        $this->post(route('intelligence.reporte-mensual.proyectos.avances.store', [$seguimiento, $this->proyecto(self::BPIN_PLANEACION), $actividadAjena]), ['cantidad' => 1])->assertNotFound();
        $this->post(route('intelligence.reporte-mensual.proyectos.enviar', [$seguimiento, $ajeno]))->assertNotFound();

        $this->assertSame(0, EjecucionFinanciera::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, AvanceFisico::query()->withoutGlobalScopes()->count());
    }

    public function test_el_sector_no_ve_lineas_de_pasiva_techos_ni_evidencias_de_otro_sector(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $evidenciaAjena = $this->evidenciaDe($seguimiento, $this->proyecto(self::BPIN_AGRICULTURA), $this->agricultura);

        $this->actingAs($this->sectorPlaneacion);
        $this->assertSame([$this->planeacion->id], PasivaLinea::query()->distinct()->pluck('dependencia_id')->all());
        $this->assertSame([$this->planeacion->id], Techo::query()->distinct()->pluck('dependencia_id')->all());
        $this->assertSame([self::BPIN_PLANEACION], Proyecto::query()->pluck('bpin')->all());
        $this->assertSame(0, Evidencia::query()->count());

        $this->followingRedirects()
            ->get(route('intelligence.reporte-mensual.pasiva-lineas.index', $seguimiento))
            ->assertOk()
            ->assertSee(self::BPIN_PLANEACION)
            ->assertDontSee(self::BPIN_AGRICULTURA)
            ->assertDontSee('2023005500070');
        $this->get(route('intelligence.reporte-mensual.evidencias.show', $evidenciaAjena))->assertNotFound();
        $this->delete(route('intelligence.reporte-mensual.evidencias.destroy', $evidenciaAjena))->assertNotFound();

        $sinDependencia = User::factory()->create(['role' => UserRole::Member]);
        $this->actingAs($sinDependencia);
        $this->assertSame(0, Proyecto::query()->count());
        $this->followingRedirects()->get(route('intelligence.reporte-mensual.show', $seguimiento))->assertForbidden();
    }

    public function test_los_conteos_abren_el_detalle_del_modal_compartido_solo_para_el_sector_dueno(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $propio = $this->proyecto(self::BPIN_PLANEACION);

        $this->actingAs($this->sectorPlaneacion)
            ->followingRedirects()
            ->get(route('intelligence.reporte-mensual.datos-base', $seguimiento))
            ->assertSee('data-count-detail-url="'.e(route('intelligence.reporte-mensual.proyectos.detalle', [$seguimiento, $propio, 'dependencia' => $this->planeacion->id, 'relacion' => 'lineas'])).'"', false)
            ->assertSee('id="count-detail-dialog"', false);

        $this->getJson(route('intelligence.reporte-mensual.proyectos.detalle', [$seguimiento, $propio, 'relacion' => 'lineas']))
            ->assertOk()
            ->assertJsonPath('registro.codigo', self::BPIN_PLANEACION)
            ->assertJsonPath('total', 4)
            ->assertJsonPath('grupos.0.titulo', '20 — Ingresos corrientes de libre destinación')
            ->assertJsonCount(2, 'grupos.0.items');

        $this->getJson(route('intelligence.reporte-mensual.proyectos.detalle', [$seguimiento, $this->proyecto(self::BPIN_AGRICULTURA), 'relacion' => 'lineas']))->assertNotFound();
    }

    public function test_solo_la_gerencia_crea_seguimientos_carga_pasivas_y_ajusta_techos(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $techoPropio = Techo::query()->where('dependencia_id', $this->planeacion->id)->firstOrFail();
        $techoAjeno = Techo::query()->where('dependencia_id', $this->agricultura->id)->firstOrFail();

        $this->actingAs($this->sectorPlaneacion);
        $this->post(route('intelligence.reporte-mensual.store'), ['vigencia' => 2026, 'mes' => 9])->assertForbidden();
        $this->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => $this->archivoFixture()])->assertForbidden();
        $this->post(route('intelligence.reporte-mensual.techos.ajustar', $techoPropio), ['valor' => 1, 'motivo' => 'Intento del sector', 'soporte' => UploadedFile::fake()->create('s.pdf')])->assertForbidden();
        $this->post(route('intelligence.reporte-mensual.techos.ajustar', $techoAjeno), ['valor' => 1, 'motivo' => 'Intento del sector', 'soporte' => UploadedFile::fake()->create('s.pdf')])->assertNotFound();

        $this->actingAs($this->gerencia)
            ->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => $this->archivoFixture()])
            ->assertForbidden();
        $this->post(route('intelligence.reporte-mensual.historicas.diagnosticar', $seguimiento), [
            'archivo' => UploadedFile::fake()->createWithContent('historico.csv', "codigo_meta;avance\n31011014003;1\n"),
        ])->assertForbidden();

        $this->actingAs($this->gerencia)
            ->post(route('intelligence.reporte-mensual.techos.ajustar', $techoPropio), ['valor' => 400000000, 'motivo' => 'Adición aprobada por decreto pendiente en PCT', 'soporte' => UploadedFile::fake()->create('decreto.pdf', 20, 'application/pdf')])
            ->assertRedirect();

        $techoPropio->refresh();
        $this->assertSame('400000000.00', $techoPropio->valor);
        $ultimo = $techoPropio->historial()->first();
        $this->assertSame(OrigenCambioTecho::AjusteManual, $ultimo->origen);
        $this->assertSame($this->gerencia->id, $ultimo->user_id);
        $this->assertNotNull($ultimo->soporte_sha256);
    }

    public function test_la_ejecucion_que_supera_el_techo_de_la_pasiva_se_rechaza(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_PLANEACION);
        $actividad = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $this->planeacion->id]);
        $otra = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $this->planeacion->id]);
        $ruta = route('intelligence.reporte-mensual.proyectos.ejecucion.update', [$seguimiento, $proyecto]);

        $this->actingAs($this->sectorPlaneacion)
            ->put($ruta, ['ejecucion' => [['actividad_id' => $actividad->id, 'fuente_financiacion_id' => $this->propios->id, 'comprometido' => 200000000, 'obligado' => 100000000, 'pagado' => 50000000]]])
            ->assertSessionHasNoErrors();

        $this->put($ruta, ['ejecucion' => [['actividad_id' => $otra->id, 'fuente_financiacion_id' => $this->propios->id, 'comprometido' => 108000001, 'obligado' => 0, 'pagado' => 0]]])
            ->assertSessionHasErrors(['ejecucion.fuente.'.$this->propios->id => '20 — Ingresos corrientes de libre destinación: lo reportado ($308.000.001) supera el techo de la pasiva ($308.000.000). Saldo disponible: $108.000.000.']);

        $this->put($ruta, ['ejecucion' => [['actividad_id' => $otra->id, 'fuente_financiacion_id' => $this->sgr->id, 'comprometido' => 10, 'obligado' => 20, 'pagado' => 0]]])
            ->assertSessionHasErrors('ejecucion.0');

        $this->put($ruta, ['ejecucion' => [['actividad_id' => $otra->id, 'fuente_financiacion_id' => $this->propios->id, 'comprometido' => 108000000, 'obligado' => 0, 'pagado' => 0]]])
            ->assertSessionHasNoErrors();

        $this->assertSame(308000000.0, (float) EjecucionFinanciera::query()->sum('comprometido'));
    }

    public function test_no_se_puede_guardar_un_avance_fisico_sin_evidencia(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_PLANEACION);
        $actividad = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $this->planeacion->id]);

        $this->actingAs($this->sectorPlaneacion)
            ->post(route('intelligence.reporte-mensual.proyectos.avances.store', [$seguimiento, $proyecto, $actividad]), ['cantidad' => 3, 'fecha_ejecucion' => '2026-08-20', 'descripcion' => 'Boletines publicados'])
            ->assertSessionHasErrors(['evidencias' => 'Adjunte al menos una evidencia para reportar avance físico.']);
        $this->assertSame(0, AvanceFisico::query()->count());
        $this->post(route('intelligence.reporte-mensual.proyectos.enviar', [$seguimiento, $proyecto]))
            ->assertSessionHasErrors('envio');

        $archivo = UploadedFile::fake()->create('boletin.pdf', 12, 'application/pdf');
        $this->post(route('intelligence.reporte-mensual.proyectos.avances.store', [$seguimiento, $proyecto, $actividad]), ['cantidad' => 3, 'fecha_ejecucion' => '2026-08-20', 'evidencias' => [$archivo], 'descripcion_evidencia' => 'Boletín de agosto'])
            ->assertSessionHasNoErrors();

        $evidencia = Evidencia::query()->sole();
        Storage::disk('local')->assertExists($evidencia->path);
        $this->assertSame(hash('sha256', (string) Storage::disk('local')->get($evidencia->path)), $evidencia->sha256);
        $this->assertSame($this->sectorPlaneacion->id, $evidencia->uploaded_by);

        $this->post(route('intelligence.reporte-mensual.proyectos.enviar', [$seguimiento, $proyecto]))->assertSessionHasNoErrors();
        $this->assertSame(EstadoReporteProyecto::Reportado, ReporteProyecto::query()->sole()->estado);

        $this->post(route('intelligence.reporte-mensual.proyectos.avances.store', [$seguimiento, $proyecto, $actividad]), ['cantidad' => 5, 'fecha_ejecucion' => '2026-08-21'])->assertForbidden();
    }

    public function test_la_focalizacion_multimunicipio_debe_sumar_100_y_justificar_cambios(): void
    {
        $agosto = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_PLANEACION);
        $proyecto->update(['tipo_focalizacion' => TipoFocalizacion::Multimunicipio]);
        [$villavicencio, $acacias] = [Municipio::factory()->create(['codigo_dane' => '50001']), Municipio::factory()->create(['codigo_dane' => '50006'])];
        $ruta = fn (Seguimiento $seguimiento): string => route('intelligence.reporte-mensual.proyectos.focalizacion.update', [$seguimiento, $proyecto]);

        $this->actingAs($this->sectorPlaneacion)
            ->post(route('intelligence.reporte-mensual.proyectos.enviar', [$agosto, $proyecto]))
            ->assertSessionHasErrors('envio');

        $this->put($ruta($agosto), ['focalizacion' => [['municipio_id' => $villavicencio->id, 'porcentaje' => 60], ['municipio_id' => $acacias->id, 'porcentaje' => 30]]])
            ->assertSessionHasErrors(['focalizacion' => 'La focalización por municipio suma 90%; debe sumar 100%.']);

        $this->put($ruta($agosto), ['focalizacion' => [['municipio_id' => $villavicencio->id, 'porcentaje' => 60], ['municipio_id' => $acacias->id, 'porcentaje' => 40]]])
            ->assertSessionHasNoErrors();
        $this->post(route('intelligence.reporte-mensual.proyectos.enviar', [$agosto, $proyecto]))->assertSessionHasNoErrors();

        $septiembre = Seguimiento::factory()->create(['vigencia' => 2026, 'mes' => 9, 'created_by' => $this->gerencia->id]);
        $this->actingAs($this->admin)->post(route('intelligence.reporte-mensual.pasivas.store', $septiembre), ['archivo' => $this->archivoFixture()]);

        $this->actingAs($this->sectorPlaneacion)
            ->get(route('intelligence.reporte-mensual.proyectos.show', [$septiembre, $proyecto]))
            ->assertOk()
            ->assertSee('60 %');

        $this->put($ruta($septiembre), ['focalizacion' => [['municipio_id' => $villavicencio->id, 'porcentaje' => 50], ['municipio_id' => $acacias->id, 'porcentaje' => 50]]])
            ->assertSessionHasErrors('justificacion_focalizacion');

        $this->put($ruta($septiembre), ['focalizacion' => [['municipio_id' => $villavicencio->id, 'porcentaje' => 50], ['municipio_id' => $acacias->id, 'porcentaje' => 50]], 'justificacion_focalizacion' => 'Se amplió la cobertura en Acacías.'])
            ->assertSessionHasNoErrors();

        $municipal = $this->proyecto(self::BPIN_PLANEACION);
        $municipal->update(['tipo_focalizacion' => TipoFocalizacion::Municipal]);
        $this->put($ruta($septiembre), ['focalizacion' => [['municipio_id' => $villavicencio->id, 'porcentaje' => 100]]])
            ->assertSessionHasErrors('focalizacion');
    }

    public function test_un_seguimiento_cerrado_queda_congelado(): void
    {
        $seguimiento = $this->seguimientoConPasiva();
        $proyecto = $this->proyecto(self::BPIN_PLANEACION);
        $actividad = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $this->planeacion->id]);
        $ruta = route('intelligence.reporte-mensual.proyectos.ejecucion.update', [$seguimiento, $proyecto]);
        $fila = ['actividad_id' => $actividad->id, 'fuente_financiacion_id' => $this->propios->id, 'comprometido' => 1000, 'obligado' => 0, 'pagado' => 0];

        $this->actingAs($this->sectorPlaneacion)->put($ruta, ['ejecucion' => [$fila]])->assertSessionHasNoErrors();

        $this->actingAs($this->sectorPlaneacion)->post(route('intelligence.reporte-mensual.cerrar', $seguimiento))->assertForbidden();
        $this->actingAs($this->gerencia)->post(route('intelligence.reporte-mensual.cerrar', $seguimiento))->assertSessionHasNoErrors();
        $this->assertTrue($seguimiento->fresh()->estaCerrado());

        $this->actingAs($this->sectorPlaneacion)->put($ruta, ['ejecucion' => [['comprometido' => 2000] + $fila]])->assertForbidden();
        $this->actingAs($this->admin)->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => $this->archivoFixture()])->assertForbidden();

        $ejecucion = EjecucionFinanciera::query()->withoutGlobalScopes()->sole();
        $this->assertCongelado(fn () => $ejecucion->update(['comprometido' => 5]));
        $this->assertCongelado(fn () => $ejecucion->delete());
        $this->assertCongelado(fn () => Techo::query()->withoutGlobalScopes()->first()->update(['valor_pasiva' => 1]));
        $this->assertCongelado(fn () => PasivaLinea::query()->withoutGlobalScopes()->first()->delete());
        $this->assertCongelado(fn () => $seguimiento->fresh()->update(['observacion' => 'Reabrir']));
        $this->assertSame('1000.00', $ejecucion->fresh()->comprometido);

        $this->actingAs($this->sectorPlaneacion)
            ->get(route('intelligence.reporte-mensual.proyectos.show', [$seguimiento, $proyecto]))
            ->assertOk()
            ->assertDontSee('Guardar ejecución');
    }

    private function seguimientoConPasiva(): Seguimiento
    {
        $this->actingAs($this->gerencia)
            ->post(route('intelligence.reporte-mensual.store'), ['vigencia' => 2026, 'mes' => 8])
            ->assertRedirect();

        $seguimiento = Seguimiento::query()->where(['vigencia' => 2026, 'mes' => 8])->sole();

        $this->actingAs($this->admin)
            ->post(route('intelligence.reporte-mensual.pasivas.store', $seguimiento), ['archivo' => $this->archivoFixture()])
            ->assertRedirect(route('intelligence.reporte-mensual.show', $seguimiento))
            ->assertSessionHasNoErrors();

        return $seguimiento;
    }

    private function rutaFixture(): string
    {
        return base_path('tests/Fixtures/reporte_sectorial/pasiva_agosto_2026.csv');
    }

    private function archivoFixture(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('pasiva_agosto_2026.csv', (string) file_get_contents($this->rutaFixture()));
    }

    private function proyecto(string $bpin): Proyecto
    {
        return Proyecto::query()->withoutGlobalScopes()->where('bpin', $bpin)->sole();
    }

    private function assertTecho(Seguimiento $seguimiento, string $bpin, FuenteFinanciacion $fuente, Dependencia $dependencia, float $valor): Techo
    {
        $techo = Techo::query()->withoutGlobalScopes()
            ->where('seguimiento_id', $seguimiento->id)
            ->where('proyecto_id', $this->proyecto($bpin)->id)
            ->where('fuente_financiacion_id', $fuente->id)
            ->where('dependencia_id', $dependencia->id)
            ->sole();

        $this->assertEqualsWithDelta($valor, (float) $techo->valor, 0.001);

        return $techo;
    }

    private function evidenciaDe(Seguimiento $seguimiento, Proyecto $proyecto, Dependencia $dependencia): Evidencia
    {
        $actividad = Actividad::factory()->create(['proyecto_id' => $proyecto->id, 'dependencia_id' => $dependencia->id]);
        $reporte = ReporteProyecto::query()->withoutGlobalScopes()->create(['seguimiento_id' => $seguimiento->id, 'proyecto_id' => $proyecto->id, 'dependencia_id' => $dependencia->id]);
        $avance = AvanceFisico::query()->withoutGlobalScopes()->create(['reporte_proyecto_id' => $reporte->id, 'actividad_id' => $actividad->id, 'cantidad' => 1, 'fecha_ejecucion' => '2026-08-10']);

        return Evidencia::query()->withoutGlobalScopes()->create([
            'avance_fisico_id' => $avance->id,
            'disk' => 'local',
            'path' => 'reporte-sectorial/evidencias/ajena.pdf',
            'nombre_original' => 'ajena.pdf',
            'mime' => 'application/pdf',
            'bytes' => 10,
            'sha256' => str_repeat('a', 64),
            'uploaded_by' => $this->sectorAgricultura->id,
        ]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function xlsxMetas(array $rows, string $name = 'metas_a_agosto.xlsx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'metas').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * @return list<string>
     */
    private function encabezadosMetas(): array
    {
        return [
            'PILAR',
            'EJE',
            'LINEA',
            'PROGRAMA',
            'SUBPROGRAMA',
            'SECTOR',
            'META RESULTADO',
            'INDICADOR RESULTADO',
            'COD. META PRODUCTO',
            'META PRODUCTO',
            'RESPONSABLE',
            '% AVANCE FINANCIERO',
            'PROGRAMACION FISICA',
            'AVANCE FISICO',
            '% AVANCE FISICO',
            'ASIGNADO',
            'COMPROMETIDO',
            'OBLIGADO',
            'OBSERVACIONES',
        ];
    }

    /**
     * @return list<string>
     */
    private function encabezadosProyectos(): array
    {
        return [
            'PILAR DE GOBIERNO',
            'EJE ESTRATEGICO',
            'LINEA ESTRATEGICA',
            'PROGRAMA',
            'SUBPROGRAMA',
            'SECTOR (MGA) - CATALOGO SISPT',
            'META RESULTADO',
            'INDICADOR RESULTADO',
            'COD. META PRODUCTO',
            'META PRODUCTO',
            'LINEA BASE',
            'INDICADOR PRODUCTO',
            'ORIENTACION',
            'ODS',
            'TRAZADOR PRESUPUESTAL',
            'CATEGORIA TRAZADOR',
            'RESPONSABLE',
            'BPIN',
            'NOMBRE DE PROYECTO',
            'ENTIDAD EJECUTORA',
            'FUENTE DE FINANCIACION',
            'ASIGNACION POR FUENTE',
            'COMPROMISOS POR FUENTE',
            'OBLIGADO POR FUENTE',
            '% AVANCE FINANCIERO',
            'PROGRAMACION FISICA',
            'AVANCE FISICO',
            '% AVANCE FISICO',
            'ASIGNADO',
            'COMPROMETIDO',
            'OBLIGADO',
            '',
        ];
    }

    private function assertCongelado(callable $accion): void
    {
        try {
            $accion();
        } catch (CorteCerradoException) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Se esperaba CorteCerradoException en un seguimiento cerrado.');
    }
}
