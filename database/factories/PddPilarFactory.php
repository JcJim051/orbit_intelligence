<?php

namespace Database\Factories;

use App\Models\PddPilar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PddPilar>
 */
class PddPilarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('###########'),
            'numeral' => (string) fake()->numberBetween(1, 9),
            'nombre' => fake()->sentence(3),
            'activo' => true,
        ];
    }
}
