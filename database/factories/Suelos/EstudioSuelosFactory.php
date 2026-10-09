<?php

namespace Database\Factories\Suelos;

use App\Models\Dependencia;
use App\Models\Municipio;
use App\Models\Suelos\EstadoValidacion;
use App\Models\Suelos\EstudioSuelos;
use App\Models\Suelos\TipoEstudio;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EstudioSuelos>
 */
class EstudioSuelosFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'ES-'.fake()->unique()->numerify('2026-######'),
            'titulo' => 'Estudio de suelos de prueba',
            'tipo_estudio_id' => fn () => TipoEstudio::query()->where('codigo', 'EDIF')->firstOrFail()->id,
            'dependencia_id' => Dependencia::factory(),
            'investment_project_id' => null,
            'investment_contract_id' => null,
            'sin_bpin_justificacion' => 'Estudio anterior al registro del proyecto en el banco de proyectos.',
            'consultor_nombre' => 'Firma consultora S.A.S.',
            'fecha_estudio' => '2024-06-01',
            'municipio_id' => Municipio::factory(),
            'estado_validacion_id' => fn () => EstadoValidacion::query()->where('codigo', 'CARGADO')->firstOrFail()->id,
            'cargado_por' => User::factory(),
        ];
    }

    public function validado(): static
    {
        return $this->state(fn (): array => [
            'estado_validacion_id' => EstadoValidacion::query()->where('codigo', 'VALIDADO')->firstOrFail()->id,
        ]);
    }
}
