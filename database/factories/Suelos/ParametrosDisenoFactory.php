<?php

namespace Database\Factories\Suelos;

use App\Enums\PotencialExpansivo;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\ParametrosDiseno;
use App\Models\Suelos\PerfilSueloNsr10;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParametrosDiseno>
 */
class ParametrosDisenoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estudio_id' => EstudioSuelos::factory(),
            'exploracion_id' => null,
            'perfil_suelo_nsr10_id' => fn () => PerfilSueloNsr10::query()->where('codigo', 'D')->firstOrFail()->id,
            'capacidad_portante_adm_kpa' => 120,
            'potencial_expansivo' => PotencialExpansivo::Bajo,
        ];
    }
}
