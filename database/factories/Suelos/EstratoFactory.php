<?php

namespace Database\Factories\Suelos;

use App\Models\Suelos\ClasificacionUscs;
use App\Models\Suelos\Estrato;
use App\Models\Suelos\Exploracion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Estrato>
 */
class EstratoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exploracion_id' => Exploracion::factory(),
            'profundidad_desde_m' => 0,
            'profundidad_hasta_m' => 2,
            'clasificacion_uscs_id' => fn () => ClasificacionUscs::query()->where('codigo', 'CL')->firstOrFail()->id,
            'descripcion' => 'Arcilla de baja plasticidad, color café',
            'es_relleno' => false,
        ];
    }
}
