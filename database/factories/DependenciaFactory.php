<?php

namespace Database\Factories;

use App\Enums\TipoDependencia;
use App\Models\Dependencia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dependencia>
 */
class DependenciaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('DEP-###'),
            'nombre' => fake()->company(),
            'sigla' => fake()->lexify('???'),
            'tipo' => TipoDependencia::Central,
            'hoja_matriz' => fake()->bothify('HOJA-###'),
            'activo' => true,
        ];
    }
}
