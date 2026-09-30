<?php

namespace Database\Factories;

use App\Models\IndicadorResultado;
use App\Models\MetaResultado;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaResultado>
 */
class MetaResultadoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => null,
            'codigo_provisional' => 'MR-'.fake()->unique()->numerify('###'),
            'descripcion' => fake()->sentence(6),
            'programa_id' => null,
            'subprograma_id' => null,
            'indicador_resultado_id' => IndicadorResultado::factory(),
            'linea_base' => null,
            'meta_cuatrienio' => null,
            'observacion' => null,
            'activo' => true,
        ];
    }
}
