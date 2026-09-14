<?php

namespace Database\Factories;

use App\Models\InvestmentProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestmentProject>
 */
class InvestmentProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bpin' => fake()->unique()->numerify('202600#########'),
            'name' => fake()->sentence(6),
            'objective' => fake()->sentence(14),
            'status' => 'En ejecución',
            'horizon' => '2024-2027',
            'horizon_start_year' => 2024,
            'horizon_end_year' => 2027,
            'sector' => fake()->randomElement(['Transporte', 'Educación', 'Salud']),
            'responsible_entity' => 'Meta',
            'responsible_entity_code' => '50',
            'total_value' => 1000000000,
            'current_value' => 800000000,
            'paid_value' => 400000000,
            'is_governor_meta' => true,
            'is_territory_meta' => true,
            'is_ecosystem_meta' => true,
            'source_dataset_id' => 'cf9k-55fw',
            'last_synced_at' => now(),
            'raw_data' => [],
        ];
    }
}
