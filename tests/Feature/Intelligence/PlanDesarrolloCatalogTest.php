<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Models\Dependencia;
use App\Models\IndicadorResultado;
use App\Models\MetaProducto;
use App\Models\MetaResultado;
use App\Models\PddEje;
use App\Models\PddLinea;
use App\Models\PddPilar;
use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use App\Models\SectorMga;
use App\Models\User;
use App\Services\Intelligence\Catalogs\MetaResultadoCatalog;
use Database\Seeders\EstructuraPlanDesarrolloSeeder;
use Database\Seeders\MetaResultadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class PlanDesarrolloCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_seeders_load_the_plan_and_report_unmatched_result_goals(): void
    {
        $this->seed(EstructuraPlanDesarrolloSeeder::class);
        $this->seed(MetaResultadoSeeder::class);

        $this->assertSame(5, PddPilar::query()->count());
        $this->assertSame(5, PddEje::query()->count());
        $this->assertSame(35, PddLinea::query()->count());
        $this->assertSame(80, PddPrograma::query()->count());
        $this->assertSame(215, PddSubprograma::query()->count());
        $this->assertSame(16, SectorMga::query()->count());
        $this->assertSame(482, MetaProducto::query()->count());
        $this->assertSame(122, MetaResultado::query()->count());
        $this->assertSame(98, IndicadorResultado::query()->count());
        $this->assertSame(1, MetaResultado::query()->whereNull('indicador_resultado_id')->count());
        $this->assertSame(0, MetaResultado::query()->whereNotNull('codigo')->count());
        $this->assertSame(0, IndicadorResultado::query()->whereNotNull('codigo')->count());
        $this->assertFalse(Schema::hasTable('meta_resultado_indicador'));
        $this->assertSame(8, PddPrograma::query()->whereNull('numeral')->count());

        $this->assertDatabaseHas('pdd_pilares', [
            'codigo' => '10000000000',
            'numeral' => '1',
            'nombre' => 'SEGURIDAD TOTAL Y DERECHOS HUMANOS EN EL META',
        ]);
        $this->assertDatabaseHas('pdd_programas', [
            'codigo' => '11022000000',
            'numeral' => '1.1.2.2',
        ]);
        $this->assertDatabaseHas('pdd_subprogramas', [
            'codigo' => '11023020000',
            'numeral' => '1.1.2.3.2',
        ]);
        $this->assertDatabaseHas('sectores_mga', [
            'codigo' => '04',
            'nombre' => 'Información Estadística',
        ]);

        $enfermedades = PddSubprograma::query()->where('codigo', '41072080000')->firstOrFail();
        $this->assertSame('41072000000', $enfermedades->programa->codigo);
        $apoyo = PddSubprograma::query()->where('codigo', '11012020000')->firstOrFail();
        $this->assertSame('11012000000', $apoyo->programa->codigo);

        $this->assertDatabaseMissing('metas_producto', ['codigo' => '21053013201']);

        $report = $this->reportRows();
        $unmatched = array_values(array_unique(array_column(
            array_filter($report, fn (array $row): bool => $row['tipo'] === 'codigo_sin_produccion'),
            'referencia',
        )));
        sort($unmatched);

        $this->assertSame([
            '21053013201',
            '21061023202',
            '21061023203',
            '21083033502',
            '21093013301',
            '21101041301',
            '41053014102',
            '41072051901',
            '41072081901',
            '41072081904',
            '41072081905',
            '41072081906',
            '41072131901',
            '41082012201',
            '41093030000',
            '41984012201',
            '61011024501',
            '61011034501',
            '61011034502',
            '61011034503',
        ], $unmatched);

        $withoutProducts = MetaResultado::query()->whereDoesntHave('metasProducto')->count();
        $withoutPrograma = MetaResultado::query()->whereNull('programa_id')->count();
        $this->assertSame($withoutProducts, $this->reportCount($report, 'sin_metas_producto'));
        $this->assertSame($withoutPrograma, $this->reportCount($report, 'sin_programa'));
        $this->assertSame(14, $withoutProducts);
        $this->assertSame(30, $withoutPrograma);
        $this->assertGreaterThan(0, MetaProducto::query()->whereNotNull('meta_resultado_id')->count());

        $this->seed(EstructuraPlanDesarrolloSeeder::class);
        $this->seed(MetaResultadoSeeder::class);
        $this->assertSame(122, MetaResultado::query()->count());
        $this->assertSame(98, IndicadorResultado::query()->count());
        $this->assertSame(482, MetaProducto::query()->count());
    }

    public function test_result_goal_endpoint_returns_the_indicator_chain_and_product_goals(): void
    {
        $this->seed(EstructuraPlanDesarrolloSeeder::class);
        $this->seed(MetaResultadoSeeder::class);

        $meta = MetaResultado::query()
            ->has('metasProducto')
            ->whereNotNull('programa_id')
            ->whereNotNull('indicador_resultado_id')
            ->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->get(route('intelligence.metas-resultado.consulta', $meta))->assertRedirect(route('login'));
        $this->get(route('intelligence.estructura'))->assertRedirect(route('login'));

        $this->actingAs($member)
            ->getJson(route('intelligence.estructura'))
            ->assertOk()
            ->assertJsonCount(5, 'pilares')
            ->assertJsonPath('pilares.0.codigo', '10000000000');

        $response = $this->actingAs($admin)
            ->getJson(route('intelligence.metas-resultado.consulta', $meta))
            ->assertOk()
            ->assertJsonPath('codigo', null)
            ->assertJsonPath('codigo_provisional', $meta->codigo_provisional)
            ->assertJsonPath('indicador.nombre', $meta->indicador->nombre)
            ->assertJsonPath('cadena.programa.codigo', $meta->programa->codigo)
            ->assertJsonPath('cadena.pilar.codigo', $meta->programa->linea->eje->pilar->codigo);

        $response->assertJsonPath('metas_producto.0.codigo', $meta->metasProducto()->orderBy('codigo')->value('codigo'));

        $this->actingAs($member)
            ->followingRedirects()
            ->get(route('intelligence.metas-producto.index', ['meta_resultado_id' => $meta->id]))
            ->assertOk()
            ->assertSee($meta->metasProducto()->value('codigo'))
            ->assertDontSee('Descargar todo');

        $this->actingAs($member)
            ->followingRedirects()
            ->get(route('intelligence.metas-resultado.index', [
                'programa_id' => $meta->programa_id,
                'indicador_resultado_id' => $meta->indicador_resultado_id,
            ]))
            ->assertOk()
            ->assertSee($meta->codigo_provisional);
    }

    public function test_a_parent_with_active_children_cannot_be_deleted_and_a_soft_deleted_child_does_not_block(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $eje = PddEje::factory()->create();
        $pilar = $eje->pilar;

        $this->actingAs($admin)
            ->delete(route('intelligence.pilares.destroy', $pilar))
            ->assertSessionHasErrors('eliminacion');

        $this->assertNotSoftDeleted($pilar);

        $eje->delete();

        $this->actingAs($admin)
            ->delete(route('intelligence.pilares.destroy', $pilar))
            ->assertRedirect(route('intelligence.pilares.index'));

        $this->assertSoftDeleted($pilar);

        $indicador = IndicadorResultado::factory()->create();
        $meta = MetaResultado::factory()->create(['indicador_resultado_id' => $indicador->id]);

        $this->actingAs($admin)
            ->delete(route('intelligence.indicadores-resultado.destroy', $indicador))
            ->assertSessionHasErrors('eliminacion');

        $this->assertNotSoftDeleted($indicador);
        $this->assertNotNull($meta->fresh());

        $dependencia = Dependencia::factory()->create();
        MetaProducto::factory()->create(['dependencia_id' => $dependencia->id]);

        $this->actingAs($admin)
            ->delete(route('intelligence.dependencias.destroy', $dependencia))
            ->assertSessionHasErrors('eliminacion');

        $this->assertNotSoftDeleted($dependencia);
    }

    public function test_excel_import_upserts_by_code_and_rolls_back_the_whole_file(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->post(route('intelligence.pilares.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'numeral', 'nombre', 'activo'],
                ['10000000000', '1', 'Pilar importado', 1],
                ['20000000000', '2', 'Segundo pilar', 1],
            ]),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame(2, PddPilar::query()->count());

        $this->actingAs($admin)->post(route('intelligence.pilares.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'numeral', 'nombre', 'activo'],
                ['10000000000', '1', 'No debe quedar', 1],
                ['99', '9', 'Código corto', 1],
            ]),
        ])->assertSessionHasErrors('importacion');

        $this->assertSame('Pilar importado', PddPilar::query()->where('codigo', '10000000000')->value('nombre'));
        $this->assertDatabaseMissing('pdd_pilares', ['codigo' => '99']);

        $indicador = IndicadorResultado::factory()->create(['nombre' => 'Tasa de ejemplo']);
        $programa = PddPrograma::factory()->create(['codigo' => '11011000000']);
        $subprograma = PddSubprograma::factory()->create([
            'codigo' => '11011010000',
            'programa_id' => $programa->id,
        ]);
        $otro = PddSubprograma::factory()->create(['codigo' => '99000000000']);

        $headers = (new MetaResultadoCatalog)->excelHeaders();
        $this->assertSame('codigo_provisional', $headers[1]);

        $this->actingAs($admin)->post(route('intelligence.metas-resultado.import'), [
            'archivo' => $this->xlsx([
                $headers,
                ['', 'MR-900', 'Meta importada', '11011000000', '11011010000', 'Tasa de ejemplo', '', '', '', 1],
            ]),
        ])->assertRedirect()->assertSessionHas('status');

        $meta = MetaResultado::query()->where('codigo_provisional', 'MR-900')->firstOrFail();
        $this->assertNull($meta->codigo);
        $this->assertSame($indicador->id, $meta->indicador_resultado_id);
        $this->assertSame($programa->id, $meta->programa_id);
        $this->assertSame($subprograma->id, $meta->subprograma_id);

        $this->actingAs($admin)->post(route('intelligence.metas-resultado.import'), [
            'archivo' => $this->xlsx([
                $headers,
                ['', 'MR-900', 'No aplicar', '11011000000', '11011010000', 'Tasa de ejemplo', '', '', '', 1],
                ['', 'MR-901', 'Meta inválida', '11011000000', '11011010000', 'Indicador inexistente', '', '', '', 1],
            ]),
        ])->assertSessionHasErrors('importacion');

        $this->assertSame('Meta importada', $meta->fresh()->descripcion);
        $this->assertDatabaseMissing('metas_resultado', ['codigo_provisional' => 'MR-901']);

        $this->actingAs($admin)->post(route('intelligence.metas-resultado.import'), [
            'archivo' => $this->xlsx([
                $headers,
                ['', 'MR-900', 'Tampoco', '11011000000', $otro->codigo, 'Tasa de ejemplo', '', '', '', 1],
            ]),
        ])->assertSessionHasErrors('importacion');

        $this->assertSame($subprograma->id, $meta->fresh()->subprograma_id);
    }

    /**
     * @param  list<array<string, string>>  $report
     */
    private function reportCount(array $report, string $type): int
    {
        return count(array_filter($report, fn (array $row): bool => $row['tipo'] === $type));
    }

    /**
     * @return list<array<string, string>>
     */
    private function reportRows(): array
    {
        $path = storage_path('app/seed-reports/metas_resultado.csv');
        $this->assertFileExists($path);
        $handle = fopen($path, 'rb');
        $this->assertNotFalse($handle);
        $header = fgetcsv($handle);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, $data);
        }

        fclose($handle);

        return $rows;
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
}
