<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Enums\EstadoSincronizacionCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaSincronizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapaSincronizacion>
 */
class CapaSincronizacionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'capa_id' => Capa::factory(),
            'iniciado_at' => '2026-03-01 12:00:00',
            'finalizado_at' => '2026-03-01 12:04:00',
            'fecha_corte_detectada' => '2026-02-28 00:00:00',
            'cambio' => true,
            'entidades_agregadas' => 2,
            'entidades_actualizadas' => 1,
            'entidades_eliminadas' => 0,
            'estado' => EstadoSincronizacionCapa::Exitoso,
            'mensaje_error' => null,
        ];
    }

    public function sinCambios(): static
    {
        return $this->state(fn (): array => [
            'cambio' => false,
            'entidades_agregadas' => 0,
            'entidades_actualizadas' => 0,
            'entidades_eliminadas' => 0,
            'estado' => EstadoSincronizacionCapa::SinCambios,
            'mensaje_error' => null,
        ]);
    }
}
