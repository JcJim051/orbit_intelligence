<?php

namespace Database\Factories;

use App\Models\PddEje;
use App\Models\PddLinea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PddLinea>
 */
class PddLineaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('###########'),
            'numeral' => '1.1.1',
            'nombre' => fake()->sentence(3),
            'eje_id' => PddEje::factory(),
            'activo' => true,
        ];
    }
}
