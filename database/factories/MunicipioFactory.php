<?php

namespace Database\Factories;

use App\Models\Municipio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Municipio>
 */
class MunicipioFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo_dane' => fake()->unique()->numerify('50###'),
            'nombre' => fake()->city(),
            'subregion' => 'Ariari',
            'activo' => true,
        ];
    }
}
