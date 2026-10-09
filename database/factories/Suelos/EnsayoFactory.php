<?php

namespace Database\Factories\Suelos;

use App\Models\Suelos\Ensayo;
use App\Models\Suelos\Exploracion;
use App\Models\Suelos\TipoEnsayo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ensayo>
 */
class EnsayoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exploracion_id' => Exploracion::factory(),
            'profundidad_desde_m' => 1,
            'tipo_ensayo_id' => fn () => TipoEnsayo::query()->where('codigo', 'W_NAT')->firstOrFail()->id,
            'valor' => 25,
            'es_rechazo' => false,
        ];
    }
}
