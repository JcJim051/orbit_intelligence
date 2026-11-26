<?php

namespace Database\Factories\Suelos;

use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\Exploracion;
use App\Models\Suelos\MetodoCoordenadas;
use App\Models\Suelos\SistemaCoordenadas;
use App\Models\Suelos\TipoExploracion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exploracion>
 */
class ExploracionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'estudio_id' => EstudioSuelos::factory(),
            'codigo' => 'S-'.fake()->unique()->numerify('###'),
            'tipo_exploracion_id' => fn () => TipoExploracion::query()->where('codigo', 'SPT')->firstOrFail()->id,
            'x_original' => -73.7670,
            'y_original' => 3.9870,
            'sistema_coordenadas_id' => fn () => SistemaCoordenadas::query()->where('codigo', 'EPSG:4326')->firstOrFail()->id,
            'metodo_coordenadas_id' => fn () => MetodoCoordenadas::query()->where('codigo', 'GNSS_NAV')->firstOrFail()->id,
            'cota_msnm' => 520,
            'profundidad_total_m' => 6,
            'nivel_freatico_encontrado' => true,
            'nivel_freatico_m' => 2.4,
        ];
    }
}
