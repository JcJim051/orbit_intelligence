<?php

namespace Tests\Feature\Admin;

use App\Enums\DatasetFieldType;
use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use App\Models\SpatialImportContract;
use App\Models\User;
use App\Services\Postgis\ProvisionSpatialImportStaging;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class SpatialImportContractControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_editable_contract_and_preserves_staging_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Profiled,
            'profile' => ['tables' => [[
                'name' => 'Puntos Críticos',
                'row_count' => 2,
                'columns' => [
                    ['name' => 'id', 'data_type' => 'integer', 'udt_name' => 'int4', 'nullable' => false],
                    ['name' => 'Población afectada', 'data_type' => 'integer', 'udt_name' => 'int4', 'nullable' => true],
                    ['name' => 'geom', 'data_type' => 'USER-DEFINED', 'udt_name' => 'geometry', 'nullable' => true],
                ],
                'geometries' => [['column' => 'geom', 'type' => 'POINT', 'srid' => 4326]],
            ]]],
        ]);
        $this->mock(ProvisionSpatialImportStaging::class, fn (MockInterface $mock) => $mock->shouldReceive('freeze')->once()->withArgs(fn (SpatialImport $bound, string $table): bool => $bound->is($import) && $table === 'Puntos Críticos'));

        $this->actingAs($admin)->post(route('admin.spatial-imports.contract.store', $import), [
            'table' => 'Puntos Críticos',
            'name' => 'Puntos críticos',
            'slug' => 'puntos-criticos-importados',
            'description' => 'Contrato institucional inicial.',
        ])->assertRedirect(route('admin.spatial-datasets.index'))->assertSessionHas('status');

        $import->refresh();
        $this->assertSame(SpatialImportStatus::ContractDraft, $import->status);
        $this->assertSame('dato_id', $import->field_mapping['id']);
        $this->assertSame('poblacion_afectada', $import->field_mapping['Población afectada']);
        $this->assertSame('point', $import->dataset->geometry_type);
        $this->assertSame(4326, $import->dataset->storage_srid);
        $field = $import->dataset->versions()->firstOrFail()->fields()->where('key', 'poblacion_afectada')->firstOrFail();
        $this->assertSame(DatasetFieldType::Integer, $field->field_type);
        $this->assertFalse($field->public_visible);
        $this->assertDatabaseHas('audit_logs', ['event' => 'spatial_import_contract_drafted', 'actor_id' => $admin->id]);
    }

    public function test_admin_can_incorporate_a_qgis_table_with_a_trailing_space_in_its_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Profiled,
            'profile' => ['tables' => [$this->profiledTable('San juanito Final ', 9377)]],
        ]);
        $this->mock(ProvisionSpatialImportStaging::class, fn (MockInterface $mock) => $mock->shouldReceive('freeze')->once()->withArgs(
            fn (SpatialImport $bound, string $table): bool => $bound->is($import) && $table === 'San juanito Final ',
        ));

        $this->actingAs($admin)->get(route('admin.spatial-imports.index'))
            ->assertSee('name="table_encoded" value="'.base64_encode('San juanito Final ').'"', false);

        $this->post(route('admin.spatial-imports.contract.store', $import), [
            'table_encoded' => base64_encode('San juanito Final '),
            'name' => 'San juanito Final',
            'slug' => 'san-juanito-final',
            'storage_srid' => 9377,
        ])->assertRedirect(route('admin.spatial-datasets.index'))->assertSessionHas('status');

        $this->assertDatabaseHas('spatial_import_contracts', [
            'spatial_import_id' => $import->id,
            'source_table' => 'San juanito Final ',
        ]);
    }

    public function test_rejects_an_invalid_encoded_table_name(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Profiled,
            'profile' => ['tables' => [$this->profiledTable('San juanito Final ', 9377)]],
        ]);

        $this->actingAs($admin)->post(route('admin.spatial-imports.contract.store', $import), [
            'table_encoded' => 'not-base64!',
            'name' => 'San juanito Final',
            'slug' => 'san-juanito-final',
            'storage_srid' => 9377,
        ])->assertRedirect()->assertSessionHasErrors('table');

        $this->assertDatabaseMissing('spatial_datasets', ['slug' => 'san-juanito-final']);
    }

    public function test_rejects_table_not_present_in_latest_profile(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create(['status' => SpatialImportStatus::Profiled, 'profile' => ['tables' => []]]);

        $this->actingAs($admin)->post(route('admin.spatial-imports.contract.store', $import), [
            'table' => 'otra_tabla', 'name' => 'Otra', 'slug' => 'otra',
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertNull($import->fresh()->spatial_dataset_id);
    }

    public function test_import_contract_can_preserve_origin_national_coordinates(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Profiled,
            'profile' => ['tables' => [[
                'name' => 'limites_meta',
                'row_count' => 1,
                'columns' => [
                    ['name' => 'nombre', 'data_type' => 'text', 'udt_name' => 'text', 'nullable' => true],
                    ['name' => 'geom', 'data_type' => 'USER-DEFINED', 'udt_name' => 'geometry', 'nullable' => true],
                ],
                'geometries' => [['column' => 'geom', 'type' => 'POLYGON', 'srid' => 9377]],
            ]]],
        ]);
        $this->mock(ProvisionSpatialImportStaging::class, fn (MockInterface $mock) => $mock->shouldReceive('freeze')->once()->withArgs(fn (SpatialImport $bound, string $table): bool => $bound->is($import) && $table === 'limites_meta'));

        $this->actingAs($admin)->post(route('admin.spatial-imports.contract.store', $import), [
            'table' => 'limites_meta',
            'name' => 'Límites del Meta',
            'slug' => 'limites-meta-origen-nacional',
            'storage_srid' => 9377,
        ])->assertRedirect(route('admin.spatial-datasets.index'))->assertSessionHas('status');

        $this->assertSame(9377, $import->fresh()->dataset->storage_srid);
    }

    public function test_import_contract_rejects_a_layer_without_identified_source_crs(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Profiled,
            'profile' => ['tables' => [[
                'name' => 'capa_sin_crs',
                'row_count' => 1,
                'columns' => [['name' => 'nombre', 'data_type' => 'text', 'udt_name' => 'text', 'nullable' => true]],
                'geometries' => [['column' => 'geom', 'type' => 'POLYGON', 'srid' => 0]],
            ]]],
        ]);

        $this->actingAs($admin)->post(route('admin.spatial-imports.contract.store', $import), [
            'table' => 'capa_sin_crs',
            'name' => 'Capa sin CRS',
            'slug' => 'capa-sin-crs',
            'storage_srid' => 9377,
        ])->assertRedirect()->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'CRS de origen'));

        $this->assertNull($import->fresh()->spatial_dataset_id);
    }

    public function test_admin_incorporates_second_layer_from_approved_qgis_zone_without_changing_first_contract(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $firstDataset = SpatialDataset::factory()->create(['slug' => 'diferendo-meta-caqueta']);
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Approved,
            'selected_table' => 'diferendo_meta_caqueta',
            'field_mapping' => ['OBJECTID' => 'o_b_j_e_c_t_i_d'],
            'spatial_dataset_id' => $firstDataset->id,
            'profile' => ['tables' => [
                $this->profiledTable('diferendo_meta_caqueta', 4326),
                $this->profiledTable('drenaje_doble_4326', 4326),
            ]],
        ]);
        SpatialImportContract::factory()->create([
            'spatial_import_id' => $import->id,
            'source_table' => 'diferendo_meta_caqueta',
            'spatial_dataset_id' => $firstDataset->id,
            'status' => SpatialImportStatus::Approved,
        ]);
        $this->mock(ProvisionSpatialImportStaging::class, fn (MockInterface $mock) => $mock->shouldReceive('freeze')->once()->withArgs(fn (SpatialImport $bound, string $table): bool => $bound->is($import) && $table === 'drenaje_doble_4326'));

        $this->actingAs($admin)->post(route('admin.spatial-imports.contract.store', $import), [
            'table' => 'drenaje_doble_4326',
            'name' => 'Drenaje doble',
            'slug' => 'drenaje-doble',
            'storage_srid' => 4326,
        ])->assertRedirect(route('admin.spatial-datasets.index'))->assertSessionHas('status');

        $import->refresh();
        $this->assertSame(SpatialImportStatus::Approved, $import->status);
        $this->assertSame($firstDataset->id, $import->spatial_dataset_id);
        $this->assertSame('diferendo_meta_caqueta', $import->selected_table);
        $this->assertDatabaseHas('spatial_import_contracts', [
            'spatial_import_id' => $import->id,
            'source_table' => 'drenaje_doble_4326',
            'status' => SpatialImportStatus::ContractDraft->value,
        ]);
        $this->assertSame(2, $import->contracts()->count());
        $this->assertDatabaseHas('spatial_datasets', ['slug' => 'drenaje-doble', 'status' => 'draft']);

        $this->actingAs($admin)->get(route('admin.spatial-imports.index'))
            ->assertOk()
            ->assertSee('drenaje_doble_4326')
            ->assertSee('Revisar conjunto')
            ->assertDontSee('Para llevar una de ellas al catálogo, cree una autorización de carga independiente.');
    }

    public function test_same_source_table_cannot_be_incorporated_twice(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $existingDataset = SpatialDataset::factory()->create();
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Approved,
            'selected_table' => 'capa_inicial',
            'spatial_dataset_id' => $existingDataset->id,
            'profile' => ['tables' => [$this->profiledTable('capa_inicial', 4326)]],
        ]);

        $this->actingAs($admin)->post(route('admin.spatial-imports.contract.store', $import), [
            'table' => 'capa_inicial', 'name' => 'Duplicada', 'slug' => 'capa-duplicada', 'storage_srid' => 4326,
        ])->assertRedirect()->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'ya tiene un contrato'));

        $this->assertDatabaseMissing('spatial_datasets', ['slug' => 'capa-duplicada']);
    }

    /** @return array<string, mixed> */
    private function profiledTable(string $name, int $srid): array
    {
        return [
            'name' => $name,
            'row_count' => 1,
            'columns' => [
                ['name' => 'nombre', 'data_type' => 'text', 'udt_name' => 'text', 'nullable' => true],
                ['name' => 'geom', 'data_type' => 'USER-DEFINED', 'udt_name' => 'geometry', 'nullable' => true],
            ],
            'geometries' => [['column' => 'geom', 'type' => 'MULTIPOLYGON', 'srid' => $srid]],
        ];
    }
}
