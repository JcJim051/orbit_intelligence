<?php

namespace Database\Factories;

use App\Models\TipologiaInversion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TipologiaInversion> */
class TipologiaInversionFactory extends Factory
{
    protected $model = TipologiaInversion::class;

    public function definition(): array
    {
        $code = 'TI'.fake()->unique()->numberBetween(1, 5);

        return [
            'codigo' => $code,
            'nombre' => fake()->sentence(3),
            'descripcion' => fake()->sentence(),
            'orden' => (int) substr($code, 2),
            'activo' => true,
        ];
    }
}
