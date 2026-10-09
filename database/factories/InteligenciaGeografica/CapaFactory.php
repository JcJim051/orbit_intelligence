<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Enums\EstadoFrescuraCapa;
use App\Enums\FrecuenciaActualizacionCapa;
use App\Enums\TipoAccesoCapa;
use App\Models\InteligenciaGeografica\Capa;
use App\Models\InteligenciaGeografica\Fuente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Capa>
 */
class CapaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => fake()->unique()->bothify('CAPA-####'),
            'nombre' => fake()->sentence(3),
            'fuente_id' => Fuente::factory(),
            'tipo_acceso' => TipoAccesoCapa::Rest,
            'url_servicio' => null,
            'layer_id' => null,
            'pregunta' => '¿Qué información de esta capa intersecta el polígono?',
            'frecuencia_actualizacion' => FrecuenciaActualizacionCapa::Mensual,
            'ttl_horas' => 720,
            'estado_frescura' => EstadoFrescuraCapa::PosiblementeDesactualizada,
            'cita_fuente' => 'Fuente de prueba. Capa de prueba. URL pendiente de confirmación. Licencia pendiente de confirmación.',
            'activa' => true,
            'observacion' => 'TODO: confirmar URL exacta.',
        ];
    }
}
