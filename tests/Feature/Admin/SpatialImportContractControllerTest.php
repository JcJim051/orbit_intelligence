<?php

namespace Tests\Feature\Admin;

use App\Enums\DatasetFieldType;
use App\Enums\SpatialImportStatus;
use App\Enums\UserRole;
use App\Models\SpatialImport;
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
        $this->mock(ProvisionSpatialImportStaging::class, fn (MockInterface $mock) => $mock->shouldReceive('freeze')->once()->withArgs(fn (SpatialImport $bound): bool => $bound->is($import)));

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
        $this->mock(ProvisionSpatialImportStaging::class, fn (MockInterface $mock) => $mock->shouldReceive('freeze')->once());

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
}
