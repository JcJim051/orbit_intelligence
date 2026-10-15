<?php

namespace Tests\Feature\Intelligence;

use App\Enums\UserRole;
use App\Models\MetaProducto;
use App\Models\TipologiaInversion;
use App\Models\User;
use Database\Seeders\TipologiaInversionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class TipologiaInversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_seeder_creates_the_five_fixed_investment_typologies(): void
    {
        $this->seed(TipologiaInversionSeeder::class);

        $this->assertSame(['TI1', 'TI2', 'TI3', 'TI4', 'TI5'], TipologiaInversion::query()->orderBy('orden')->pluck('codigo')->all());
        $this->assertSame('TI1 — Impacto territorial (infraestructura y obra)', TipologiaInversion::query()->where('codigo', 'TI1')->firstOrFail()->etiqueta());
    }

    public function test_meta_producto_uses_the_catalog_foreign_key(): void
    {
        $typology = TipologiaInversion::factory()->create(['codigo' => 'TI1']);
        $meta = MetaProducto::factory()->create(['tipologia_inversion_id' => $typology->id]);

        $this->assertSame($typology->id, $meta->fresh()->tipologiaInversion->id);
        $this->assertDatabaseHas('metas_producto', ['id' => $meta->id, 'tipologia_inversion_id' => $typology->id]);
    }

    public function test_admin_imports_typology_by_code_and_empty_foreign_keys_do_not_clear_existing_values(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->seed(TipologiaInversionSeeder::class);
        $typology = TipologiaInversion::query()->where('codigo', 'TI2')->firstOrFail();
        $meta = MetaProducto::factory()->create(['tipologia_inversion_id' => null]);
        $dependency = $meta->dependencia;

        $this->actingAs($admin)->post(route('intelligence.metas-producto.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'nombre', 'indicador_producto', 'subprograma_codigo', 'sector_codigo', 'meta_resultado_codigo', 'dependencia_codigo', 'tipologia_inversion_codigo', 'activo'],
                [$meta->codigo, $meta->nombre, $meta->indicador_producto, $meta->subprograma->codigo, $meta->sectorMga->codigo, null, $dependency?->codigo, 'TI2', 1],
            ]),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame($typology->id, $meta->fresh()->tipologia_inversion_id);
        $this->assertNotNull($meta->fresh()->tipologia_clasificada_at);

        $this->actingAs($admin)->post(route('intelligence.metas-producto.import'), [
            'archivo' => $this->xlsx([
                ['codigo', 'nombre', 'indicador_producto', 'subprograma_codigo', 'sector_codigo', 'meta_resultado_codigo', 'dependencia_codigo', 'tipologia_inversion_codigo', 'activo'],
                [$meta->codigo, $meta->nombre, $meta->indicador_producto, $meta->subprograma->codigo, $meta->sectorMga->codigo, null, null, null, 1],
            ]),
        ])->assertRedirect()->assertSessionHas('status');

        $this->assertSame($typology->id, $meta->fresh()->tipologia_inversion_id);
    }

    public function test_only_admin_can_mutate_typology_catalog(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($admin)->post(route('intelligence.tipologias-inversion.store'), [
            'codigo' => 'TI1', 'nombre' => 'Impacto', 'descripcion' => 'Descripción', 'orden' => 1, 'activo' => 1,
        ])->assertRedirect();

        $record = TipologiaInversion::query()->firstOrFail();
        $this->actingAs($member)->followingRedirects()->get(route('intelligence.tipologias-inversion.index'))->assertOk()->assertDontSee('Nuevo registro');
        $this->actingAs($member)->get(route('intelligence.tipologias-inversion.create'))->assertForbidden();
        $this->actingAs($member)->patch(route('intelligence.tipologias-inversion.update', $record), [])->assertForbidden();
        $this->actingAs($member)->delete(route('intelligence.tipologias-inversion.destroy', $record))->assertForbidden();
    }

    /** @param list<list<mixed>> $rows */
    private function xlsx(array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'tipologia').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        foreach ($rows as $row) { $writer->addRow(Row::fromValues($row)); }
        $writer->close();

        return new UploadedFile($path, 'tipologias.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
