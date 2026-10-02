<?php

namespace Database\Factories;

use App\Enums\TipoFocalizacion;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proyecto>
 */
class ProyectoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bpin' => '2025'.fake()->unique()->numerify('#########'),
            'nombre' => mb_strtoupper(fake()->sentence(6)),
            'tipo_focalizacion' => TipoFocalizacion::Municipal,
            'activo' => true,
        ];
    }

    public function multimunicipio(): static
    {
        return $this->state(fn (): array => ['tipo_focalizacion' => TipoFocalizacion::Multimunicipio]);
    }
}
