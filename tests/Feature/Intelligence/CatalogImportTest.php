<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\DependenciaReglaPasiva;
use App\Models\Municipio;
use App\Models\User;
use App\Services\Intelligence\CatalogWorkbook;
use Database\Seeders\DependenciaSeeder;
use Database\Seeders\MunicipioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_seeders_load_the_matrix_dependencies_and_meta_municipalities_without_pasiva_rules(): void
    {
        $this->seed(DependenciaSeeder::class);
        $this->seed(MunicipioSeeder::class);

        $this->assertSame(27, Dependencia::query()->count());
        $this->assertSame(29, Municipio::query()->count());
        $this->assertSame(0, DependenciaReglaPasiva::query()->count());
        $this->assertDatabaseHas('dependencias', [
            'codigo' => 'SEC. SALUD',
            'nombre' => 'SECRETARIA DE SALUD',
            'tipo' => 'central',
            'hoja_matriz' => 'SEC. SALUD',
            'activo' => true,
        ]);
        $this->assertDatabaseHas('dependencias', ['codigo' => 'FSES', 'tipo' => 'por_confirmar']);
        $this->assertDatabaseHas('municipios', [
            'codigo_dane' => '50001',
            'nombre' => 'Villavicencio',
            'subregion' => 'Capital-Cordillera',
        ]);
        $this->assertDatabaseHas('municipios', ['codigo_dane' => '50006', 'nombre' => 'Acacías']);

        $this->seed(DependenciaSeeder::class);
        $this->seed(MunicipioSeeder::class);

        $this->assertSame(27, Dependencia::query()->count());
        $this->assertSame(29, Municipio::query()->count());
    }

    public function test_administrator_creates_updates_and_soft_deletes_a_dependency(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('intelligence.dependencias.store'), [])
            ->assertInvalid(['codigo' => 'El código es obligatorio.']);

        $this->actingAs($admin)->post(route('intelligence.dependencias.store'), [
            'codigo' => 'SEC. SALUD',
            'nombre' => 'Secretaría de Salud',
            'sigla' => 'SALUD',
            'tipo' => 'central',
            'hoja_matriz' => 'SEC. SALUD',
            'activo' => '1',
        ])->assertRedirect(route('intelligence.dependencias.index'));

        $dependencia = Dependencia::query()->where('codigo', 'SEC. SALUD')->firstOrFail();

        $this->actingAs($admin)->patch(route('intelligence.dependencias.update', $dependencia), [
            'codigo' => 'SEC. SALUD',
            'nombre' => 'Secretaría de Salud del Meta',
            'sigla' => 'SALUD',
            'tipo' => 'central',
            'hoja_matriz' => 'SEC. SALUD',
            'activo' => '1',
        ])->assertRedirect(route('intelligence.dependencias.index'));

        $this->assertSame('Secretaría de Salud del Meta', $dependencia->fresh()->nombre);

        $this->actingAs($admin)->delete(route('intelligence.dependencias.destroy', $dependencia))
            ->assertRedirect(route('intelligence.dependencias.index'));

        $this->assertSoftDeleted($dependencia);
    }

    public function test_deactivating_a_municipality_keeps_the_row_and_marks_it_inactive(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $municipio = Municipio::factory()->create(['activo' => true]);

        $this->actingAs($admin)->delete(route('intelligence.municipios.destroy', $municipio))
            ->assertRedirect(route('intelligence.municipios.index'));

        $this->assertFalse($municipio->fresh()->activo);
        $this->assertDatabaseHas('municipios', ['id' => $municipio->id]);
    }

    public function test_invalid_dane_code_is_rejected_in_spanish(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('intelligence.municipios.store'), [
            'codigo_dane' => '50',
            'nombre' => 'Incompleto',
            'subregion' => 'Ariari',
            'activo' => '1',
        ])->assertInvalid(['codigo_dane' => 'El código DANE debe tener 5 dígitos.']);

        $this->assertDatabaseMissing('municipios', ['nombre' => 'Incompleto']);
    }

    public function test_list_filters_by_status_and_search(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Dependencia::factory()->create(['codigo' => 'SEC. SALUD', 'nombre' => 'Secretaría de Salud', 'activo' => true]);
        Dependencia::factory()->create(['codigo' => 'AIM', 'nombre' => 'Agencia de infraestructura', 'activo' => false]);

        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.dependencias.index', ['q' => 'Salud']))
            ->assertOk()
            ->assertSee('SEC. SALUD')
            ->assertDontSee('AIM');

        $this->actingAs($admin)->followingRedirects()->get(route('intelligence.dependencias.index', ['activo' => '0']))
            ->assertOk()
            ->assertSee('AIM')
            ->assertDontSee('SEC. SALUD');
    }

    public function test_template_has_exact_headers_an_example_row_and_allowed_values(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $dependencias = $this->sheets($admin, 'intelligence.dependencias.template');
        $this->assertSame(
            ['codigo', 'nombre', 'sigla', 'tipo', 'hoja_matriz', 'activo'],
            $dependencias['Datos'][0],
        );
        $this->assertSame('SEC. EJEMPLO', $dependencias['Datos'][1][0]);
        $instructions = implode(' ', array_merge(...$dependencias['Instrucciones']));
        $this->assertStringContainsString('central', $instructions);
        $this->assertStringContainsString('descentralizado', $instructions);
        $this->assertStringContainsString('por_confirmar', $instructions);

        $municipios = $this->sheets($admin, 'intelligence.municipios.template');
        $this->assertSame(['codigo_dane', 'nombre', 'subregion', 'activo'], $municipios['Datos'][0]);
        $this->assertCount(2, $municipios['Datos']);

        $reglas = $this->sheets($admin, 'intelligence.reglas-pasiva.template');
        $this->assertSame(
            ['dependencia_codigo', 'tipo_regla', 'valor', 'prioridad', 'vigencia_desde', 'vigencia_hasta', 'observacion', 'activo'],
            $reglas['Datos'][0],
        );
        $ruleInstructions = implode(' ', array_merge(...$reglas['Instrucciones']));
        $this->assertStringContainsString('unidad_pct', $ruleInstructions);
        $this->assertStringContainsString('prefijo_rubro', $ruleInstructions);
        $this->assertStringContainsString('sector_mga', $ruleInstructions);
        $this->assertStringContainsString('bpin', $ruleInstructions);
    }

    public function test_import_upserts_by_code_and_rolls_back_when_any_row_is_invalid(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Dependencia::factory()->create([
            'codigo' => 'AIM',
            'nombre' => 'Nombre original',
            'tipo' => 'descentralizado',
            'activo' => true,
        ]);

        $this->actingAs($admin)->post(route('intelligence.dependencias.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'nombre', 'sigla', 'tipo', 'hoja_matriz', 'activo'],
                ['AIM', 'Agencia actualizada', 'AIM', 'descentralizado', 'AIM', 1],
                ['EDESA', 'Empresa de servicios', 'EDESA', 'descentralizado', 'EDESA', 1],
            ]),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, Dependencia::query()->count());
        $this->assertDatabaseHas('dependencias', ['codigo' => 'AIM', 'nombre' => 'Agencia actualizada']);
        $this->assertDatabaseHas('dependencias', ['codigo' => 'EDESA', 'nombre' => 'Empresa de servicios']);

        $this->actingAs($admin)->post(route('intelligence.dependencias.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'nombre', 'sigla', 'tipo', 'hoja_matriz', 'activo'],
                ['AIM', 'No debe guardarse', 'AIM', 'descentralizado', 'AIM', 1],
                ['NUEVA', '', 'NUEVA', 'central', 'NUEVA', 1],
            ]),
        ])->assertSessionHasErrors('importacion');

        $this->assertSame('Agencia actualizada', Dependencia::query()->where('codigo', 'AIM')->value('nombre'));
        $this->assertDatabaseMissing('dependencias', ['codigo' => 'NUEVA']);
        $this->assertStringContainsString('Fila 3:', session('errors')->get('fila_0')[0]);
    }

    public function test_importing_the_same_code_restores_a_soft_deleted_dependency(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $dependencia = Dependencia::factory()->create(['codigo' => 'AIM', 'nombre' => 'Eliminada']);
        $dependencia->delete();

        $this->actingAs($admin)->post(route('intelligence.dependencias.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'nombre', 'sigla', 'tipo', 'hoja_matriz', 'activo'],
                ['AIM', 'Agencia restaurada', 'AIM', 'descentralizado', 'AIM', 1],
            ]),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertNull($dependencia->fresh()->deleted_at);
        $this->assertSame('Agencia restaurada', $dependencia->fresh()->nombre);
        $this->assertSame(1, Dependencia::query()->count());
    }

    public function test_rule_import_upserts_by_dependency_type_and_value_and_rolls_back_together(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $salud = Dependencia::factory()->create(['codigo' => 'SEC. SALUD']);
        DependenciaReglaPasiva::factory()->create([
            'dependencia_id' => $salud->id,
            'tipo_regla' => 'bpin',
            'valor' => '2023005500070',
            'prioridad' => 400,
            'observacion' => 'Original',
        ]);

        $this->actingAs($admin)->post(route('intelligence.reglas-pasiva.import'), [
            'archivo' => $this->xlsx([
                ['dependencia_codigo', 'tipo_regla', 'valor', 'prioridad', 'vigencia_desde', 'vigencia_hasta', 'observacion', 'activo'],
                ['SEC. SALUD', 'bpin', '2023005500070', 400, null, null, 'Actualizada', 1],
                ['SEC. SALUD', 'unidad_pct', '0301', null, null, null, null, 1],
            ]),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, DependenciaReglaPasiva::query()->count());
        $this->assertDatabaseHas('dependencia_reglas_pasiva', [
            'dependencia_id' => $salud->id,
            'valor' => '2023005500070',
            'observacion' => 'Actualizada',
        ]);
        $this->assertDatabaseHas('dependencia_reglas_pasiva', [
            'dependencia_id' => $salud->id,
            'tipo_regla' => 'unidad_pct',
            'valor' => '0301',
            'prioridad' => 100,
        ]);

        $this->actingAs($admin)->post(route('intelligence.reglas-pasiva.import'), [
            'archivo' => $this->xlsx([
                ['dependencia_codigo', 'tipo_regla', 'valor', 'prioridad', 'vigencia_desde', 'vigencia_hasta', 'observacion', 'activo'],
                ['SEC. SALUD', 'bpin', '2023005500070', 400, null, null, 'No aplicar', 1],
                ['SEC. SALUD', 'bpin', '123', 400, null, null, 'BPIN corto', 1],
            ]),
        ])->assertSessionHasErrors('importacion');

        $this->assertSame('Actualizada', DependenciaReglaPasiva::query()->where('valor', '2023005500070')->value('observacion'));
        $this->assertDatabaseMissing('dependencia_reglas_pasiva', ['valor' => '123']);
        $this->assertStringContainsString('13 y 15 dígitos', session('errors')->get('fila_0')[0]);
    }

    public function test_duplicate_natural_keys_in_one_file_do_not_change_existing_rows(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        Dependencia::factory()->create(['codigo' => 'AIM', 'nombre' => 'Nombre original']);

        $this->actingAs($admin)->post(route('intelligence.dependencias.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'nombre', 'sigla', 'tipo', 'hoja_matriz', 'activo'],
                ['AIM', 'Primera', 'AIM', 'descentralizado', 'AIM', 1],
                ['AIM', 'Segunda', 'AIM', 'descentralizado', 'AIM', 1],
            ]),
        ])->assertSessionHasErrors('importacion');

        $this->assertSame('Nombre original', Dependencia::query()->where('codigo', 'AIM')->value('nombre'));
        $this->assertStringContainsString('repite la llave', session('errors')->get('fila_0')[0]);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'catalog').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return new UploadedFile($path, 'catalogo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function sheets(User $admin, string $routeName): array
    {
        $response = $this->actingAs($admin)->get(route($routeName));
        $response->assertOk();
        $source = $response->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'plantilla').'.xlsx';
        copy($source, $copy);

        return app(CatalogWorkbook::class)->namedSheets($copy);
    }
}
