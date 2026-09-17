<?php

namespace Tests\Feature;

use App\Enums\SpatialImportStatus;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SpatialImportContractMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_approved_layer_is_preserved_as_first_contract(): void
    {
        $dataset = SpatialDataset::factory()->create();
        $import = SpatialImport::factory()->create([
            'status' => SpatialImportStatus::Approved,
            'selected_table' => 'diferendo_meta_caqueta',
            'field_mapping' => ['OBJECTID' => 'o_b_j_e_c_t_i_d'],
            'spatial_dataset_id' => $dataset->id,
        ]);
        Schema::drop('spatial_import_contracts');

        $migration = require database_path('migrations/2026_09_17_015258_create_spatial_import_contracts_table.php');
        $migration->up();

        $this->assertDatabaseHas('spatial_import_contracts', [
            'spatial_import_id' => $import->id,
            'source_table' => 'diferendo_meta_caqueta',
            'spatial_dataset_id' => $dataset->id,
            'status' => 'approved',
        ]);
        $this->assertSame(['OBJECTID' => 'o_b_j_e_c_t_i_d'], $import->contracts()->firstOrFail()->field_mapping);
    }
}
