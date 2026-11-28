<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\Fuente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fuente>
 */
class FuenteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('FUENTE-###'),
            'nombre' => fake()->company(),
            'activo' => true,
        ];
    }
}
