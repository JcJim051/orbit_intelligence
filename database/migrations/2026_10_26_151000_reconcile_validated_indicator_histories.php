<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('indicadores_territoriales')
            ->where('estado', 'pendiente_historico')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('indicador_territorial_datos')
                    ->whereColumn('indicador_territorial_datos.indicador_territorial_id', 'indicadores_territoriales.id');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('indicador_territorial_datos')
                    ->whereColumn('indicador_territorial_datos.indicador_territorial_id', 'indicadores_territoriales.id')
                    ->where('estado', '!=', 'aprobado');
            })
            ->update([
                'estado' => 'historico_validado',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // La conciliación corrige estados derivados de validaciones ya existentes.
    }
};
