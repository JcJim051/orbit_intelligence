<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaObjetoCache;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<CapaObjetoCache>
 */
class CapaObjetoCacheFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'capa_id' => Capa::factory(),
            'geom' => DB::raw("ST_SetSRID(ST_GeomFromText('POINT(4916000 1998000)'), 9377)"),
            'atributos' => ['origen' => 'cache de prueba'],
            'identificador_origen' => fake()->unique()->bothify('feat-####'),
            'fetched_at' => '2026-03-01 12:00:00',
        ];
    }
}
