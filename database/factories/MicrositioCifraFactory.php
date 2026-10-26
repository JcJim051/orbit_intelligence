<?php

namespace Database\Factories;

use App\Models\MicrositioCifra;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MicrositioCifra>
 */
class MicrositioCifraFactory extends Factory
{
    protected $model = MicrositioCifra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'valor' => 'EJEMPLO',
            'descripcion' => 'Dato de ejemplo',
            'fuente' => 'Dato de ejemplo',
            'fecha_corte' => '2026-08-31',
            'es_ejemplo' => true,
            'orden' => 0,
        ];
    }
}
