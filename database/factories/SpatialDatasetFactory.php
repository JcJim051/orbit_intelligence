<?php

namespace Database\Factories;

use App\Enums\DatasetStatus;
use App\Models\SpatialDataset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpatialDataset>
 */
class SpatialDatasetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'sector' => 'Gestión del Riesgo',
            'description' => fake()->sentence(),
            'geometry_type' => 'point',
            'status' => DatasetStatus::Draft,
            'created_by' => User::factory(),
        ];
    }
}
