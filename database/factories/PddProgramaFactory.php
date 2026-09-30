<?php

namespace Database\Factories;

use App\Models\PddLinea;
use App\Models\PddPrograma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PddPrograma>
 */
class PddProgramaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('###########'),
            'numeral' => '1.1.1.1',
            'nombre' => fake()->sentence(3),
            'linea_id' => PddLinea::factory(),
            'activo' => true,
        ];
    }
}
