<?php

namespace Database\Factories;

use App\Models\MicrositioTema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MicrositioTema>
 */
class MicrositioTemaFactory extends Factory
{
    protected $model = MicrositioTema::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->lexify('tema-????'),
            'nombre' => 'Tema de ejemplo',
            'orden' => 0,
        ];
    }
}
