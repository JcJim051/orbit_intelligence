<?php

namespace Database\Factories;

use App\Enums\OrientacionIndicador;
use App\Models\IndicadorResultado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicadorResultado>
 */
class IndicadorResultadoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => null,
            'nombre' => fake()->unique()->sentence(4),
            'unidad_medida' => 'Porcentaje',
            'orientacion' => OrientacionIndicador::Incremento,
            'linea_base' => null,
            'linea_base_texto' => null,
            'meta_cuatrienio' => null,
            'fuente_verificacion' => null,
            'activo' => true,
        ];
    }
}
