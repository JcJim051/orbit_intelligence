<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                restriccion record;
            BEGIN
                FOR restriccion IN
                    SELECT con.conname
                    FROM pg_constraint con
                    JOIN pg_class rel ON rel.oid = con.conrelid
                    JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
                    WHERE nsp.nspname = 'inteligencia'
                      AND rel.relname = 'analisis_area'
                      AND con.contype = 'c'
                      AND pg_get_constraintdef(con.oid) ILIKE '%origen_geometria%'
                LOOP
                    EXECUTE format('ALTER TABLE inteligencia.analisis_area DROP CONSTRAINT %I', restriccion.conname);
                END LOOP;
            END $$;

            ALTER TABLE inteligencia.analisis_area
                ADD CONSTRAINT ck_analisis_area_origen
                CHECK (origen_geometria IN ('dibujo', 'kmz', 'kml', 'geojson'));
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.analisis_area DROP CONSTRAINT IF EXISTS ck_analisis_area_origen;
            UPDATE inteligencia.analisis_area SET origen_geometria = 'kmz' WHERE origen_geometria IN ('kml', 'geojson');
            ALTER TABLE inteligencia.analisis_area
                ADD CONSTRAINT analisis_area_origen_geometria_check
                CHECK (origen_geometria IN ('dibujo', 'kmz'));
        SQL);
    }
};
