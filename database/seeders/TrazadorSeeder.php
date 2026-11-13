<?php

namespace Database\Seeders;

use App\Models\Trazador;
use Illuminate\Database\Seeder;

class TrazadorSeeder extends Seeder
{
    public function run(): void
    {
        $trazadores = [
            ['codigo' => 'TD-001', 'nombre' => 'Primera Infancia, infancia y adolescencia'],
            ['codigo' => 'TD-002', 'nombre' => 'Equidad de la Mujer'],
            ['codigo' => 'TD-003', 'nombre' => 'Construcción de Paz'],
            ['codigo' => 'TD-004', 'nombre' => 'Comunidades N.A.R.P (Grupos Étnicos)'],
        ];

        foreach ($trazadores as $datos) {
            $trazador = Trazador::withTrashed()->firstOrNew(['codigo' => $datos['codigo']]);
            $trazador->fill([
                'nombre' => $datos['nombre'],
                'activo' => true,
            ]);
            $trazador->deleted_at = null;
            $trazador->save();
        }
    }
}
