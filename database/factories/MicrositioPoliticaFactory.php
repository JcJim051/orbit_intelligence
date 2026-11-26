<?php

namespace Database\Factories;

use App\Models\MicrositioPolitica;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MicrositioPolitica> */
class MicrositioPoliticaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(),
            'nombre' => fake()->sentence(3),
            'descripcion' => fake()->paragraph(),
            'objetivo' => fake()->paragraph(),
            'orden' => 0,
            'es_ejemplo' => true,
        ];
    }
}
