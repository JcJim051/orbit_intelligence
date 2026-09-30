<?php

namespace Database\Factories;

use App\Models\MetaProducto;
use App\Models\PddSubprograma;
use App\Models\SectorMga;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetaProducto>
 */
class MetaProductoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->numerify('###########'),
            'nombre' => fake()->sentence(8),
            'subprograma_id' => PddSubprograma::factory(),
            'sector_mga_id' => SectorMga::factory(),
            'meta_resultado_id' => null,
            'dependencia_id' => null,
            'activo' => true,
        ];
    }
}
