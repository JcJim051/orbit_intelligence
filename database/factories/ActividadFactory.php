<?php

namespace Database\Factories;

use App\Models\Actividad;
use App\Models\Dependencia;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Actividad>
 */
class ActividadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proyecto_id' => Proyecto::factory(),
            'dependencia_id' => Dependencia::factory(),
            'codigo' => fake()->bothify('ACT-##'),
            'nombre' => fake()->sentence(5),
            'unidad_medida' => 'Número',
            'cantidad_programada' => 10,
            'activo' => true,
        ];
    }
}
