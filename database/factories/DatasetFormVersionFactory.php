<?php

namespace Database\Factories;

use App\Enums\DatasetFormVersionStatus;
use App\Models\DatasetFormVersion;
use App\Models\SpatialDataset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DatasetFormVersion>
 */
class DatasetFormVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spatial_dataset_id' => SpatialDataset::factory(),
            'version' => 1,
            'status' => DatasetFormVersionStatus::Draft,
            'effective_from' => null,
            'published_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
