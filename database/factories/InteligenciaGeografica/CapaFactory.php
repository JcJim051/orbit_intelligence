<?php

namespace Database\Factories\InteligenciaGeografica;

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
            'activa' => true,
            'observacion' => 'TODO: confirmar URL exacta.',
        ];
    }
}
