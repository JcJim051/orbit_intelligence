<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fondo de teselas y un PNG por capa, para no volver a pedir OpenStreetMap.
 *
 * En SQLite no crea objetos: el informe con mapa se genera sobre PostgreSQL.
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
                ADD COLUMN mapa_base_imagen bytea;

            COMMENT ON COLUMN inteligencia.analisis_area.mapa_base_imagen IS
                'PNG de las teselas, sin polígono ni capas. Los mapas por capa lo reutilizan.';

            CREATE TABLE inteligencia.analisis_mapa_capa (
                analisis_area_id bigint NOT NULL REFERENCES inteligencia.analisis_area (id) ON DELETE CASCADE,
                capa_id bigint NOT NULL REFERENCES inteligencia.capa (id) ON DELETE CASCADE,
                imagen bytea NOT NULL,
                capturado_at timestamptz NOT NULL,
                atribucion text NOT NULL,
                nota text,
                leyenda jsonb NOT NULL,
                PRIMARY KEY (analisis_area_id, capa_id)
            );

            COMMENT ON TABLE inteligencia.analisis_mapa_capa IS
                'PNG de la intersección de una capa sobre el mapa base del análisis.';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS inteligencia.analisis_mapa_capa;

            ALTER TABLE inteligencia.analisis_area
                DROP COLUMN IF EXISTS mapa_base_imagen;
        SQL);
    }
};
