<?php

namespace Database\Factories;

use App\Enums\GrupoFuenteFinanciacion;
use App\Models\FuenteFinanciacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FuenteFinanciacion>
 */
class FuenteFinanciacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('F##?'),
            'nombre' => fake()->words(3, true),
            'tipo' => 'Propios - Libre Destinación - ICLD',
            'grupo_reporte' => GrupoFuenteFinanciacion::RecursosPropios,
            'activo' => true,
        ];
    }

    public function sgr(): static
    {
        return $this->state(fn (): array => [
            'tipo' => 'Nación - Destinacion Especifica - SGRD20%',
            'grupo_reporte' => GrupoFuenteFinanciacion::Sgr,
        ]);
    }
}
