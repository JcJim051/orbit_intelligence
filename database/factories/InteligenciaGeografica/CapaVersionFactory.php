<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use App\Models\InteligenciaGeografica\CapaVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapaVersion>
 */
class CapaVersionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'capa_id' => Capa::factory(),
            'numero' => 1,
            'fecha_corte' => '2026-02-28 00:00:00',
            'cita_fuente' => 'Instituto de prueba. Capa de prueba. https://ejemplo.test/capa. Licencia de prueba.',
            'url_fuente' => 'https://ejemplo.test/capa',
            'licencia' => 'Licencia de prueba',
            'checksum' => str_repeat('a', 64),
            'conteo' => 1,
            'agregados' => 1,
            'modificados' => 0,
            'eliminados' => 0,
            'vigente' => true,
            'sincronizacion_id' => fn (array $attributes): int => CapaSincronizacion::factory()->create([
                'capa_id' => $attributes['capa_id'],
            ])->id,
        ];
    }
}
