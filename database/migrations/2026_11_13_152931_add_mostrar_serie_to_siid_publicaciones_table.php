<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::table('siid_publicacion.publicaciones', function (Blueprint $table): void {
            $table->boolean('mostrar_serie')->default(false)->after('destacado');
        });

        DB::statement(<<<'SQL'
            INSERT INTO siid_publicacion.publicaciones
                (publicable_type, publicable_id, visible, orden, destacado, mostrar_serie, created_at, updated_at)
            SELECT
                'App\Models\IndicadorTerritorial', indicador.id, false, 0, false, false, NOW(), NOW()
            FROM public.indicadores_territoriales AS indicador
            WHERE NOT EXISTS (
                SELECT 1
                FROM siid_publicacion.publicaciones AS publicacion
                WHERE publicacion.publicable_type = 'App\Models\IndicadorTerritorial'
                  AND publicacion.publicable_id = indicador.id
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::table('siid_publicacion.publicaciones', function (Blueprint $table): void {
                $table->dropColumn('mostrar_serie');
            });
        }
    }
};
