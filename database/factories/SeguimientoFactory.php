<?php

namespace Database\Factories;

use App\Enums\EstadoSeguimiento;
use App\Models\Seguimiento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Seguimiento>
 */
class SeguimientoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vigencia' => 2026,
            'mes' => 8,
            'estado' => EstadoSeguimiento::Abierto,
            'created_by' => User::factory(),
        ];
    }
}
