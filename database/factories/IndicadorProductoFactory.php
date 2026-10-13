<?php

namespace Database\Factories;

use App\Models\IndicadorProducto;
use App\Models\MetaProducto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IndicadorProducto>
 */
class IndicadorProductoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meta_producto_id' => MetaProducto::factory(),
            'codigo' => fake()->optional()->bothify('IP-###'),
            'nombre' => fake()->sentence(7),
            'unidad_medida' => fake()->optional()->randomElement(['Número', 'Porcentaje', 'Personas', 'Kilómetros']),
            'tipo_acumulacion' => fake()->optional()->randomElement(['suma', 'constante', 'creciente']),
            'linea_base' => fake()->optional()->randomFloat(4, 0, 100),
            'meta_cuatrienio' => fake()->optional()->randomFloat(4, 0, 1000),
            'fuente_verificacion' => fake()->optional()->sentence(8),
            'activo' => true,
        ];
    }
}
