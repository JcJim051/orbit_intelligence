<?php

namespace Database\Factories;

use App\Models\PddEje;
use App\Models\PddPilar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PddEje>
 */
class PddEjeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('###########'),
            'numeral' => '1.1',
            'nombre' => fake()->sentence(3),
            'pilar_id' => PddPilar::factory(),
            'activo' => true,
        ];
    }
}
