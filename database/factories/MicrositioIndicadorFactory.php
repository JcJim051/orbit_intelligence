<?php

namespace Database\Factories;

use App\Models\MicrositioIndicador;
use App\Models\MicrositioTema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MicrositioIndicador>
 */
class MicrositioIndicadorFactory extends Factory
{
    protected $model = MicrositioIndicador::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('EJ-##'),
            'nombre' => 'Indicador de ejemplo',
            'micrositio_tema_id' => MicrositioTema::factory(),
            'dashboard_uuid' => null,
            'descripcion' => 'Dato de ejemplo',
            'es_publico' => false,
            'es_ejemplo' => true,
            'conjunto_datos' => null,
            'orden' => 0,
        ];
    }
}
