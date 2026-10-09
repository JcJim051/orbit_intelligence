<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Enums\ServidoDesde;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\InteligenciaGeografica\AnalisisResultado;
use App\Models\InteligenciaGeografica\Capa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalisisResultado>
 */
class AnalisisResultadoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'analisis_area_id' => AnalisisArea::factory(),
            'capa_id' => Capa::factory(),
            'conteo' => 0,
            'area_m2' => null,
            'longitud_m' => null,
            'resumen' => ['nota' => 'sin cruces'],
            'consulted_at' => '2026-03-01 12:05:00',
            'servido_desde' => ServidoDesde::Cache,
        ];
    }
}
