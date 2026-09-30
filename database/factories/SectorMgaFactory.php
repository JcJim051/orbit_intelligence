<?php

namespace Database\Factories;

use App\Models\SectorMga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SectorMga>
 */
class SectorMgaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('##'),
            'nombre' => fake()->words(2, true),
            'activo' => true,
        ];
    }
}
