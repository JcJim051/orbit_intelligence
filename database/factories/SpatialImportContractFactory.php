<?php

namespace Database\Factories;

use App\Enums\SpatialImportStatus;
use App\Models\SpatialDataset;
use App\Models\SpatialImport;
use App\Models\SpatialImportContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpatialImportContract>
 */
class SpatialImportContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spatial_import_id' => SpatialImport::factory(),
            'source_table' => fake()->unique()->slug(),
            'spatial_dataset_id' => SpatialDataset::factory(),
            'field_mapping' => ['nombre' => 'nombre'],
            'status' => SpatialImportStatus::ContractDraft,
        ];
    }
}
