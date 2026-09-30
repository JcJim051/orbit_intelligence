<?php

namespace Database\Factories;

use App\Models\PddPrograma;
use App\Models\PddSubprograma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PddSubprograma>
 */
class PddSubprogramaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('###########'),
            'numeral' => '1.1.1.1.1',
            'nombre' => fake()->sentence(3),
            'programa_id' => PddPrograma::factory(),
            'activo' => true,
        ];
    }
}
