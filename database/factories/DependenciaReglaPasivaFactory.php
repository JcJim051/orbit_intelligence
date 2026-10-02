<?php

namespace Database\Factories;

use App\Enums\TipoReglaPasiva;
use App\Models\Dependencia;
use App\Models\DependenciaReglaPasiva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DependenciaReglaPasiva>
 */
class DependenciaReglaPasivaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dependencia_id' => Dependencia::factory(),
            'tipo_regla' => TipoReglaPasiva::UnidadPct,
            'valor' => fake()->unique()->numerify('04####'),
            'prioridad' => TipoReglaPasiva::UnidadPct->prioridadPorDefecto(),
            'vigencia_desde' => null,
            'vigencia_hasta' => null,
            'observacion' => null,
            'activo' => true,
        ];
    }
}
