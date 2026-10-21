<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('avances_fisicos', 'reportado_fisico')) {
            Schema::table('avances_fisicos', function (Blueprint $table): void {
                $table->boolean('reportado_fisico')->default(false)->after('actividad_id');
            });
        }

        // Recupera los reportes físicos existentes. Las filas que solo tenían
        // observación permanecen en false.
        DB::table('avances_fisicos')
            ->where(function ($query): void {
                $query->where('cantidad', '>', 0)->orWhereNotNull('fecha_ejecucion');
            })
            ->update(['reportado_fisico' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('avances_fisicos', 'reportado_fisico')) {
            Schema::table('avances_fisicos', function (Blueprint $table): void {
                $table->dropColumn('reportado_fisico');
            });
        }
    }
};
