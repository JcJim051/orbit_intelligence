<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PNG del mapa del informe, guardado en el análisis para no volver a pedir teselas.
 *
 * En SQLite no crea columnas: el informe con mapa se genera sobre PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.analisis_area
                ADD COLUMN mapa_imagen bytea,
                ADD COLUMN mapa_capturado_at timestamptz,
                ADD COLUMN mapa_atribucion text,
                ADD COLUMN mapa_nota text;

            COMMENT ON COLUMN inteligencia.analisis_area.mapa_imagen IS
                'PNG del mapa del informe. Se compone una vez y los PDF siguientes lo reutilizan.';
            COMMENT ON COLUMN inteligencia.analisis_area.mapa_capturado_at IS
                'Momento en que se compuso mapa_imagen.';
            COMMENT ON COLUMN inteligencia.analisis_area.mapa_atribucion IS
                'Fuente y atribución del mapa base usado en mapa_imagen.';
            COMMENT ON COLUMN inteligencia.analisis_area.mapa_nota IS
                'Aviso si alguna tesela no se pudo descargar. El PDF se genera igual.';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.analisis_area
                DROP COLUMN IF EXISTS mapa_imagen,
                DROP COLUMN IF EXISTS mapa_capturado_at,
                DROP COLUMN IF EXISTS mapa_atribucion,
                DROP COLUMN IF EXISTS mapa_nota;
        SQL);
    }
};
