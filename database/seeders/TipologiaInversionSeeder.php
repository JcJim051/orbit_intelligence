<?php

namespace Database\Seeders;

use App\Models\TipologiaInversion;
use Illuminate\Database\Seeder;

class TipologiaInversionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['codigo' => 'TI1', 'nombre' => 'Impacto territorial (infraestructura y obra)', 'descripcion' => 'Inversión que transforma físicamente el territorio mediante infraestructura, obra o equipamiento.', 'orden' => 1],
            ['codigo' => 'TI2', 'nombre' => 'Entrega directa a comunidades', 'descripcion' => 'Bienes, servicios o apoyos entregados directamente a la población o a comunidades.', 'orden' => 2],
            ['codigo' => 'TI3', 'nombre' => 'Fortalecimiento de capacidades', 'descripcion' => 'Acciones de formación, asistencia técnica, acompañamiento o fortalecimiento organizacional.', 'orden' => 3],
            ['codigo' => 'TI4', 'nombre' => 'Preinversión y planificación', 'descripcion' => 'Estudios, diseños, estructuración, planeación y demás actividades previas a la inversión.', 'orden' => 4],
            ['codigo' => 'TI5', 'nombre' => 'Funcionamiento y operación institucional', 'descripcion' => 'Recursos destinados al funcionamiento, operación y soporte de la institucionalidad.', 'orden' => 5],
        ];

        foreach ($rows as $row) {
            TipologiaInversion::query()->updateOrCreate(
                ['codigo' => $row['codigo']],
                [...$row, 'activo' => true],
            );
        }
    }
}
