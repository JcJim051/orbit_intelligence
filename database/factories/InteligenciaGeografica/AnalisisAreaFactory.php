<?php

namespace Database\Factories\InteligenciaGeografica;

use App\Enums\EstadoAnalisisArea;
use App\Enums\OrigenGeometriaAnalisis;
use App\Models\InteligenciaGeografica\AnalisisArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<AnalisisArea>
 */
class AnalisisAreaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'investment_project_id' => null,
            'user_id' => User::factory(),
            'geom' => DB::raw("ST_SetSRID(ST_GeomFromText('MULTIPOLYGON(((4910000 1990000, 4920000 1990000, 4920000 2000000, 4910000 2000000, 4910000 1990000)))'), 9377)"),
            'origen_geometria' => OrigenGeometriaAnalisis::Dibujo,
            'nombre_archivo' => null,
            'fecha' => '2026-03-01 12:00:00',
            'estado' => EstadoAnalisisArea::Borrador,
        ];
    }
}
