<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Filament\Pages\Workspace;
use App\Models\IndicadorTerritorial;
use App\Models\IndicadorTerritorialAsignacion;
use App\Models\IndicadorTerritorialDato;
use App\Models\Dependencia;
use App\Models\User;
use App\Services\Intelligence\CatalogWorkbook;
use App\Services\Intelligence\IndicadoresTerritoriales\ImportadorHistoricoIndicador;
use App\Services\Intelligence\IndicadoresTerritoriales\ImportadorMatrizIndicadores;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class IndicadoresTerritorialesTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_reviewer_only_sees_assigned_indicators(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $other = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $own = $this->indicator(['asignado_a' => $reviewer->id, 'codigo' => 'PET-001']);
        $foreign = $this->indicator(['asignado_a' => $other->id, 'codigo' => 'PET-002']);

        $response = $this->actingAs($reviewer)->get(Workspace::getUrl(['workspace' => 'indicadores-territoriales']));

        $response->assertOk()->assertSee('PET-001')->assertDontSee('PET-002');
        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $foreign->id]))
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $own->id]))
            ->assertOk();
    }

    public function test_index_uses_an_integrated_client_side_data_table_without_the_old_filter_form(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->indicator(['codigo' => 'PET-TABLA-001']);

        $this->actingAs($manager)
            ->get(Workspace::getUrl(['workspace' => 'indicadores-territoriales']))
            ->assertOk()
            ->assertSee('data-indicator-table', false)
            ->assertSee('data-table-search', false)
            ->assertSee('Buscar en la tabla')
            ->assertSee('PET-TABLA-001')
            ->assertDontSee('name="estado"', false)
            ->assertDontSee('>Filtrar</button>', false);
    }

    public function test_index_counts_unique_dependencies_next_to_the_validated_history_summary(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $salud = Dependencia::factory()->create(['nombre' => 'Secretaría de Salud']);
        $educacion = Dependencia::factory()->create(['nombre' => 'Secretaría de Educación']);
        $this->indicator(['codigo' => 'PET-DEP-001', 'dependencia_id' => $salud->id, 'estado' => 'historico_validado']);
        $this->indicator(['codigo' => 'PET-DEP-002', 'dependencia_id' => $salud->id, 'estado' => 'historico_validado']);
        $this->indicator(['codigo' => 'PET-DEP-003', 'dependencia_id' => $educacion->id]);
        $this->indicator(['codigo' => 'PET-EXT-001', 'dependencia_fuente' => 'Entidad externa sin relación']);

        $this->actingAs($manager)
            ->get(Workspace::getUrl(['workspace' => 'indicadores-territoriales']))
            ->assertOk()
            ->assertSee('Dependencias con indicadores')
            ->assertSee('>2</strong>', false);
    }

    public function test_validated_technical_sheet_can_be_generated_from_the_institutional_word_template(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'name' => 'María Validadora',
            'email' => 'maria.validadora@meta.gov.co',
        ]);
        $indicator = $this->indicator([
            'codigo' => 'PET-DOC-001',
            'nombre' => 'Cobertura de atención territorial',
            'convencion' => 'CAT',
            'definicion' => 'Mide la cobertura territorial alcanzada.',
            'fuente_informacion' => "Sistema sectorial\nhttps://datos.meta.gov.co",
            'responsable_informacion' => 'Secretaría responsable',
            'formula_calculo' => '(Atenciones realizadas / atenciones programadas) x 100',
            'unidad_medida' => 'Porcentaje',
            'periodicidad' => 'anual',
            'periodicidad_original' => 'Anual',
            'cobertura_geografica' => 'Departamental',
            'linea_base_periodo' => '2023',
            'observaciones_tecnicas' => 'La fuente se actualiza al cierre de cada vigencia.',
            'limitaciones' => 'Depende de la oportunidad del registro administrativo.',
            'estado' => 'pendiente_historico',
            'aprobado_por' => $manager->id,
            'aprobado_at' => now(),
        ]);

        $response = $this->actingAs($manager)
            ->get(route('intelligence.indicadores-territoriales.document', $indicator));

        $response->assertOk()->assertDownload('ficha-tecnica-pet-doc-001.docx');
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $documentXml = $zip->getFromName('word/document.xml');
        $headerXml = $zip->getFromName('word/header1.xml');
        $zip->close();

        $this->assertIsString($documentXml);
        $this->assertStringContainsString('PET-DOC-001', $documentXml);
        $this->assertStringContainsString('Cobertura de atención territorial', $documentXml);
        $this->assertStringContainsString('Depende de la oportunidad del registro administrativo.', $documentXml);
        $this->assertStringContainsString('María Validadora', $documentXml);
        $this->assertStringContainsString('maria.validadora@meta.gov.co', $documentXml);
        $this->assertIsString($headerXml);
        $this->assertStringContainsString('F-PE-12', $headerXml);
    }

    public function test_unvalidated_technical_sheet_cannot_be_generated(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $indicator = $this->indicator(['codigo' => 'PET-SIN-VALIDAR']);

        $this->actingAs($manager)
            ->get(route('intelligence.indicadores-territoriales.document', $indicator))
            ->assertStatus(422);
    }

    public function test_coordinator_can_assign_a_complete_import_group_and_history_is_kept(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $this->indicator(['codigo' => 'PET-010', 'grupo_asignacion' => 'verde']);
        $this->indicator(['codigo' => 'PET-011', 'grupo_asignacion' => 'verde']);
        $yellow = $this->indicator(['codigo' => 'PET-012', 'grupo_asignacion' => 'amarillo']);

        $this->actingAs($manager)->post(route('intelligence.indicadores-territoriales.assign'), [
            'asignado_a' => $reviewer->id,
            'grupo' => 'verde',
            'motivo' => 'Distribución inicial de la matriz',
        ])->assertRedirect();

        $this->assertSame(2, IndicadorTerritorial::query()->where('asignado_a', $reviewer->id)->count());
        $this->assertNull($yellow->fresh()->asignado_a);
        $this->assertSame(2, IndicadorTerritorialAsignacion::query()->count());
    }

    public function test_only_assigned_information_reviewer_can_approve_technical_sheet(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $other = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id]);
        $payload = $this->completeTechnicalSheet();

        $this->actingAs($other)
            ->patch(route('intelligence.indicadores-territoriales.approve', $indicator), $payload)
            ->assertForbidden();

        $this->actingAs($reviewer)
            ->patch(route('intelligence.indicadores-territoriales.approve', $indicator), $payload)
            ->assertRedirect();

        $indicator->refresh();
        $this->assertSame('pendiente_historico', $indicator->estado);
        $this->assertSame($reviewer->id, $indicator->aprobado_por);
        $this->assertNotNull($indicator->aprobado_at);
    }

    public function test_assigned_reviewer_can_approve_the_saved_sheet_directly_from_show(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator([
            'asignado_a' => $reviewer->id,
            'estado' => 'revision_tecnica',
            ...$this->completeTechnicalSheet(),
        ]);

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicator->id]))
            ->assertOk()
            ->assertSee('Aprobar ficha técnica');

        $this->actingAs($reviewer)
            ->patch(route('intelligence.indicadores-territoriales.approve', $indicator))
            ->assertRedirect();

        $this->assertSame('pendiente_historico', $indicator->fresh()->estado);
    }

    public function test_coordinator_can_edit_and_approve_a_technical_sheet(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id]);
        $payload = $this->completeTechnicalSheet();

        $this->actingAs($manager)
            ->patch(route('intelligence.indicadores-territoriales.update', $indicator), $payload)
            ->assertRedirect();

        $this->assertSame('Indicador territorial validado', $indicator->fresh()->nombre);
        $this->assertSame('revision_tecnica', $indicator->fresh()->estado);

        $this->actingAs($manager)
            ->patch(route('intelligence.indicadores-territoriales.approve', $indicator), $payload)
            ->assertRedirect();

        $this->assertSame('pendiente_historico', $indicator->fresh()->estado);
    }

    public function test_historical_baseline_cannot_be_manually_changed_from_technical_sheet(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator([
            'asignado_a' => $reviewer->id,
            'linea_base_valor' => 12.5,
            'linea_base_periodo' => '2022',
        ]);

        $this->actingAs($reviewer)
            ->patch(route('intelligence.indicadores-territoriales.update', $indicator), [
                ...$this->completeTechnicalSheet(),
                'linea_base_valor' => 999,
                'linea_base_periodo' => '2099',
            ])
            ->assertRedirect();

        $indicator->refresh();
        $this->assertSame('12.500000', $indicator->linea_base_valor);
        $this->assertSame('2022', $indicator->linea_base_periodo);
    }

    public function test_approval_requires_minimum_technical_fields(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id]);

        $this->actingAs($reviewer)
            ->from(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicator->id]))
            ->patch(route('intelligence.indicadores-territoriales.approve', $indicator), [
                'nombre' => $indicator->nombre,
            ])
            ->assertSessionHasErrors(['definicion', 'unidad_medida', 'formula_calculo', 'periodicidad', 'fuente_informacion', 'responsable_informacion']);

        $this->assertSame('precargado', $indicator->fresh()->estado);
    }

    public function test_matrix_import_is_idempotent_maps_colors_and_preserves_reviewed_records(): void
    {
        $rows = [
            ['Título'],
            ['Código', 'Nombre del indicador', 'Definición', 'Unidad propuesta', 'Periodicidad original', 'Periodicidad normalizada', 'Fuente principal', 'Entidad productora', 'Dependencia en plataforma', 'Fórmula de cálculo', 'Variables', 'Área temática', 'Tipo de cálculo propuesto', 'Filas en PET', 'Fila en consolidado'],
            ['IT-0001', 'Indicador verde', 'Definición uno', 'Número', 'Mensual', 'Mensual', 'Fuente A', 'Entidad A', 'Entidad A', 'Conteo', 'Variable', 'Ambiental', 'Conteo / suma', '53', 10],
            ['IT-0002', 'Indicador sin grupo', 'Definición dos', 'Porcentaje', 'Cuando aplica', 'Combinada', 'Fuente B', 'Entidad B', 'Entidad B', 'Razón', 'Variables', 'Económica', 'Razón', '', 11],
        ];

        $importer = app(ImportadorMatrizIndicadores::class);
        $first = $importer->importRows($rows, 'prueba.xlsx');
        $this->assertSame(2, $first['created']);
        $this->assertSame('verde', IndicadorTerritorial::query()->where('codigo', 'IT-0001')->value('grupo_asignacion'));
        $this->assertSame('combinada', IndicadorTerritorial::query()->where('codigo', 'IT-0002')->value('periodicidad'));

        IndicadorTerritorial::query()->where('codigo', 'IT-0001')->update(['estado' => 'revision_tecnica', 'nombre' => 'Nombre revisado']);
        $second = $importer->importRows($rows, 'prueba.xlsx');

        $this->assertSame(1, $second['updated']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame('Nombre revisado', IndicadorTerritorial::query()->where('codigo', 'IT-0001')->value('nombre'));
        $this->assertSame(2, IndicadorTerritorial::query()->count());
    }

    public function test_versioned_production_matrix_rebuilds_all_indicator_sheets_idempotently(): void
    {
        $path = database_path('seeders/data/Matriz_consolidada_indicadores_PET.xlsx');
        $importer = app(ImportadorMatrizIndicadores::class);

        $first = $importer->import($path);
        $second = $importer->import($path);

        $this->assertSame(153, $first['total']);
        $this->assertSame(153, $first['created']);
        $this->assertSame(153, $second['updated']);
        $this->assertSame(153, IndicadorTerritorial::query()->count());
        $this->assertSame(
            'Matriz_consolidada_indicadores_PET.xlsx',
            IndicadorTerritorial::query()->value('fuente_importacion')
        );
    }

    public function test_indicator_show_combines_technical_sheet_chart_series_and_historical_upload(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id, 'estado' => 'pendiente_historico']);
        IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2025',
            'fecha_corte' => '2025-12-31',
            'valor' => 84.5,
            'observacion' => 'El resultado aumentó por la ampliación de la cobertura municipal.',
            'estado' => 'aprobado',
        ]);

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicator->id]))
            ->assertOk()
            ->assertSee('Evolución del indicador')
            ->assertSee('Observación del reporte')
            ->assertSee('El resultado aumentó por la ampliación de la cobertura municipal.')
            ->assertSee('Corte: 31/12/2025')
            ->assertSee('Gestión de datos')
            ->assertSee('Serie histórica')
            ->assertSee('Ficha técnica')
            ->assertSee('Descargar plantilla Excel');
    }

    public function test_actions_separate_show_and_technical_edit_without_exposing_delete(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id]);

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'indicadores-territoriales']))
            ->assertOk()
            ->assertSee('Acciones')
            ->assertSee('Ver')
            ->assertSee('Editar')
            ->assertDontSee('Eliminar');

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial-editar', 'record' => $indicator->id]))
            ->assertOk()
            ->assertSee('Ficha técnica del indicador')
            ->assertSee('Guardar ficha')
            ->assertDontSee('Evolución del indicador')
            ->assertDontSee('Carga histórica masiva')
            ->assertDontSee('Eliminar');
    }

    public function test_administrator_can_create_a_single_manual_report_from_series_editor(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $indicator = $this->indicator([
            'estado' => 'historico_validado',
            'unidad_medida' => 'Número',
            'fuente_informacion' => 'Registro administrativo departamental',
        ]);

        $this->actingAs($manager)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial-serie-editar', 'record' => $indicator->id]))
            ->assertOk()
            ->assertSee('Nuevo reporte')
            ->assertSee('Nuevo reporte del indicador')
            ->assertSee('Crear reporte');

        $payload = [
            'periodo' => 'Octubre 2026',
            'valor' => 37,
            'periodo_inicio' => '2026-10-01',
            'periodo_fin' => '2026-10-31',
            'fecha_corte' => '2026-10-31',
            'fuente' => 'Registro administrativo departamental',
            'enlace_evidencia' => 'https://example.test/reporte-octubre',
            'observacion' => 'Registro creado individualmente desde administración.',
        ];

        $this->actingAs($manager)
            ->post(route('intelligence.indicadores-territoriales.historico.store', $indicator), $payload)
            ->assertRedirect()
            ->assertSessionHas('status', 'Nuevo registro creado. Quedó pendiente de validación.');

        $this->assertDatabaseHas('indicador_territorial_datos', [
            'indicador_territorial_id' => $indicator->id,
            'periodo' => 'Octubre 2026',
            'valor' => 37,
            'estado' => 'pendiente',
            'origen' => 'edicion_manual',
            'reportado_por' => $manager->id,
        ]);
        $this->assertSame('historico_cargado', $indicator->fresh()->estado);

        $this->actingAs($manager)
            ->post(route('intelligence.indicadores-territoriales.historico.store', $indicator), [
                ...$payload,
                'periodo' => 'OCTUBRE 2026',
            ])
            ->assertSessionHasErrors('periodo');

        $this->assertSame(1, $indicator->datos()->count());
    }

    public function test_historical_import_creates_pending_rows_and_rejects_overwriting_approved_periods(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id, 'estado' => 'pendiente_historico', 'codigo' => 'PET-H01']);
        $path = storage_path('framework/testing/historico-indicador.xlsx');
        app(CatalogWorkbook::class)->write($path, 'Serie histórica',
            ['CODIGO_INDICADOR', 'PERIODO', 'FECHA_INICIO', 'FECHA_FIN', 'VALOR', 'FECHA_CORTE', 'FUENTE', 'ENLACE_EVIDENCIA', 'OBSERVACION'],
            [
                ['PET-H01', '2023', '2023-01-01', '2023-12-31', 18.5, '2023-12-31', 'Registro A', null, null],
                ['PET-H01', '2024', '2024-01-01', '2024-12-31', 22, '2024-12-31', 'Registro A', null, null],
            ]
        );

        $result = app(ImportadorHistoricoIndicador::class)->import($indicator, $path, $reviewer, 'historico.xlsx');

        $this->assertSame(['created' => 2, 'updated' => 0, 'total' => 2], $result);
        $this->assertSame(2, $indicator->datos()->where('estado', 'pendiente')->count());
        $this->assertSame('historico_cargado', $indicator->fresh()->estado);

        $indicator->datos()->where('periodo', '2023')->update(['estado' => 'aprobado']);
        $this->expectException(\RuntimeException::class);
        app(ImportadorHistoricoIndicador::class)->import($indicator->fresh(), $path, $reviewer, 'historico.xlsx');
    }

    public function test_approved_series_data_can_be_corrected_with_justification_and_returns_to_pending(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id, 'estado' => 'historico_validado']);
        $datum = IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2024',
            'periodo_inicio' => '2024-01-01',
            'periodo_fin' => '2024-12-31',
            'fecha_corte' => '2024-12-31',
            'valor' => 40,
            'fuente' => 'Registro administrativo',
            'estado' => 'aprobado',
            'reportado_por' => $reviewer->id,
            'validado_por' => $reviewer->id,
            'validado_at' => now(),
        ]);
        $payload = [
            'datos' => [
                $datum->id => [
                    'valor' => 42,
                    'periodo_inicio' => '2024-01-01',
                    'periodo_fin' => '2024-12-31',
                    'fecha_corte' => '2024-12-31',
                    'fuente' => 'Registro administrativo corregido',
                    'enlace_evidencia' => '',
                    'observacion' => 'Dato corregido por la fuente.',
                ],
            ],
        ];

        $this->actingAs($reviewer)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial-serie-editar', 'record' => $indicator->id]))
            ->assertOk()
            ->assertSee('Editar serie de datos')
            ->assertSee('2024');

        $this->actingAs($reviewer)
            ->patch(route('intelligence.indicadores-territoriales.historico.update', $indicator), $payload)
            ->assertSessionHasErrors('justificacion');

        $this->actingAs($reviewer)
            ->patch(route('intelligence.indicadores-territoriales.historico.update', $indicator), [
                ...$payload,
                'justificacion' => 'La dependencia corrigió el valor certificado.',
            ])
            ->assertRedirect();

        $datum->refresh();
        $this->assertSame('42.000000', $datum->valor);
        $this->assertSame('pendiente', $datum->estado);
        $this->assertNull($datum->validado_por);
        $this->assertSame('historico_cargado', $indicator->fresh()->estado);
        $this->assertDatabaseHas('indicador_territorial_dato_ediciones', [
            'indicador_territorial_dato_id' => $datum->id,
            'editado_por' => $reviewer->id,
            'justificacion' => 'La dependencia corrigió el valor certificado.',
        ]);
    }

    public function test_assigned_reviewer_or_coordinator_approves_pending_history_and_baseline_uses_oldest_period(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id, 'estado' => 'historico_cargado']);
        foreach ([['2024', '2024-01-01', 40], ['2022', '2022-01-01', 12.5]] as [$period, $start, $amount]) {
            IndicadorTerritorialDato::query()->create([
                'indicador_territorial_id' => $indicator->id,
                'periodo' => $period,
                'periodo_inicio' => $start,
                'valor' => $amount,
                'estado' => 'pendiente',
                'reportado_por' => $reviewer->id,
            ]);
        }

        $this->actingAs($manager)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial', 'record' => $indicator->id]))
            ->assertOk()
            ->assertSee('Editar serie de datos')
            ->assertSee('Aprobar cambios (2)');

        $this->actingAs($manager)
            ->patch(route('intelligence.indicadores-territoriales.historico.validate-all', $indicator))
            ->assertRedirect();

        $indicator->refresh();
        $this->assertSame('historico_validado', $indicator->estado);
        $this->assertSame('2022', $indicator->linea_base_periodo);
        $this->assertSame('12.500000', $indicator->linea_base_valor);
        $this->assertSame(2, $indicator->datos()->where('estado', 'aprobado')->count());
    }

    public function test_sector_user_cannot_edit_or_validate_a_reported_indicator_value(): void
    {
        $sector = User::factory()->create(['role' => UserRole::Member]);
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id, 'estado' => 'historico_cargado']);
        $datum = IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2025',
            'valor' => 18,
            'estado' => 'pendiente',
        ]);

        $this->actingAs($sector)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial-serie-editar', 'record' => $indicator->id]))
            ->assertForbidden();

        $this->actingAs($sector)
            ->patch(route('intelligence.indicadores-territoriales.historico.update', $indicator), [
                'datos' => [$datum->id => ['valor' => 20]],
            ])
            ->assertForbidden();

        $this->actingAs($sector)
            ->patch(route('intelligence.indicadores-territoriales.historico.validate-all', $indicator))
            ->assertForbidden();
    }

    public function test_coordinator_can_create_a_manual_indicator_and_assign_it(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);

        $this->actingAs($manager)
            ->post(route('intelligence.indicadores-territoriales.store'), [
                'codigo' => 'IT-MANUAL-001',
                'nombre' => 'Indicador creado manualmente',
                'periodicidad' => 'trimestral',
                'unidad_medida' => 'Porcentaje',
                'asignado_a' => $reviewer->id,
            ])
            ->assertRedirect();

        $indicator = IndicadorTerritorial::query()->where('codigo', 'IT-MANUAL-001')->firstOrFail();
        $this->assertSame('precargado', $indicator->estado);
        $this->assertSame('Creación manual', $indicator->fuente_importacion);
        $this->assertSame($reviewer->id, $indicator->asignado_a);
        $this->assertDatabaseHas('indicador_territorial_asignaciones', [
            'indicador_territorial_id' => $indicator->id,
            'asignado_a' => $reviewer->id,
        ]);
    }

    public function test_unit_of_measure_is_selected_from_a_controlled_list(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $indicator = $this->indicator(['unidad_medida' => 'Porcentaje']);

        $this->actingAs($manager)
            ->get(Workspace::getUrl(['workspace' => 'indicador-territorial-editar', 'record' => $indicator->id]))
            ->assertOk()
            ->assertSee('name="unidad_medida"', false)
            ->assertSee('<option value="Porcentaje" selected>Porcentaje (%)</option>', false);
    }

    public function test_information_reviewer_cannot_create_or_delete_indicators(): void
    {
        $reviewer = User::factory()->create(['role' => UserRole::InformationReviewer]);
        $indicator = $this->indicator(['asignado_a' => $reviewer->id]);

        $this->actingAs($reviewer)
            ->post(route('intelligence.indicadores-territoriales.store'), ['codigo' => 'NO-001', 'nombre' => 'No permitido'])
            ->assertForbidden();
        $this->actingAs($reviewer)
            ->delete(route('intelligence.indicadores-territoriales.destroy', $indicator))
            ->assertForbidden();
    }

    public function test_coordinator_soft_deletes_indicator_without_losing_its_history(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $indicator = $this->indicator(['estado' => 'historico_validado']);
        IndicadorTerritorialDato::query()->create([
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2024',
            'valor' => 75,
            'estado' => 'aprobado',
        ]);

        $this->actingAs($manager)
            ->delete(route('intelligence.indicadores-territoriales.destroy', $indicator))
            ->assertRedirect(route('intelligence.indicadores-territoriales.index'));

        $this->assertSoftDeleted('indicadores_territoriales', ['id' => $indicator->id]);
        $this->assertDatabaseHas('indicador_territorial_datos', [
            'indicador_territorial_id' => $indicator->id,
            'periodo' => '2024',
        ]);
    }

    private function indicator(array $overrides = []): IndicadorTerritorial
    {
        return IndicadorTerritorial::query()->create([
            'codigo' => 'PET-'.fake()->unique()->numerify('###'),
            'nombre' => 'Indicador territorial de prueba',
            'grupo_asignacion' => 'sin_asignar',
            'estado' => 'precargado',
            ...$overrides,
        ]);
    }

    /** @return array<string, string> */
    private function completeTechnicalSheet(): array
    {
        return [
            'nombre' => 'Indicador territorial validado',
            'definicion' => 'Mide el comportamiento de la variable territorial.',
            'unidad_medida' => 'Porcentaje',
            'formula_calculo' => '(Numerador / denominador) * 100',
            'periodicidad_original' => 'Mensual',
            'periodicidad' => 'mensual',
            'fuente_informacion' => 'Registro administrativo sectorial',
            'responsable_informacion' => 'Dependencia responsable',
        ];
    }
}
