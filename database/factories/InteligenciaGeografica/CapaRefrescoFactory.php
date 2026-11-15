<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Enums\EstadoRefrescoCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\CapaRefresco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CapaRefresco>
 */
class CapaRefrescoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'capa_id' => Capa::factory(),
            'iniciado_at' => '2026-03-01 12:00:00',
            'finalizado_at' => '2026-03-01 12:10:00',
            'estado' => EstadoRefrescoCapa::Exitoso,
            'registros' => 0,
            'mensaje' => null,
        ];
    }
}
