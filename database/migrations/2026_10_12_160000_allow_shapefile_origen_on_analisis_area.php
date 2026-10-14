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
            ALTER TABLE inteligencia.analisis_area DROP CONSTRAINT IF EXISTS ck_analisis_area_origen;
            ALTER TABLE inteligencia.analisis_area
                ADD CONSTRAINT ck_analisis_area_origen
                CHECK (origen_geometria IN ('dibujo', 'kmz', 'kml', 'geojson', 'shapefile'));
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.analisis_area DROP CONSTRAINT IF EXISTS ck_analisis_area_origen;
            UPDATE inteligencia.analisis_area SET origen_geometria = 'geojson' WHERE origen_geometria = 'shapefile';
            ALTER TABLE inteligencia.analisis_area
                ADD CONSTRAINT ck_analisis_area_origen
                CHECK (origen_geometria IN ('dibujo', 'kmz', 'kml', 'geojson'));
        SQL);
    }
};
