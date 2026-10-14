<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Límite departamental del Meta (DANE MGN 2025) y el recorte de cada análisis.
 *
 * En SQLite no crea objetos: la geometría y el SRID 9377 son de PostgreSQL/PostGIS.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE inteligencia.limite_referencia (
                codigo varchar(20) PRIMARY KEY,
                nombre varchar(200) NOT NULL,
                cita_fuente text NOT NULL,
                url_fuente text NOT NULL,
                fecha_corte timestamptz NOT NULL,
                geom geometry(MultiPolygon, 9377) NOT NULL,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE INDEX limite_referencia_geom_gix ON inteligencia.limite_referencia USING gist (geom);

            COMMENT ON TABLE inteligencia.limite_referencia IS
                'Polígono oficial contra el que se recorta el análisis. Las capas abiertas cubren solo el Meta.';

            ALTER TABLE inteligencia.analisis_area
                ADD COLUMN geometria_original geometry(MultiPolygon, 9377),
                ADD COLUMN geometria_analizada geometry(MultiPolygon, 9377),
                ADD COLUMN ha_fuera_meta numeric(14, 4) NOT NULL DEFAULT 0,
                ADD COLUMN pct_fuera_meta numeric(6, 2) NOT NULL DEFAULT 0;

            COMMENT ON COLUMN inteligencia.analisis_area.geometria_original IS
                'Polígono que dibujó o cargó el usuario, en EPSG:9377.';
            COMMENT ON COLUMN inteligencia.analisis_area.geometria_analizada IS
                'Intersección con el Meta. Es el área que sí se cruzó con las capas.';
            COMMENT ON COLUMN inteligencia.analisis_area.ha_fuera_meta IS
                'Hectáreas del polígono original que quedaron fuera del Meta.';
            COMMENT ON COLUMN inteligencia.analisis_area.pct_fuera_meta IS
                'Porcentaje del polígono original que quedó fuera del Meta.';

            UPDATE inteligencia.analisis_area
            SET geometria_original = geom,
                geometria_analizada = geom
            WHERE geometria_original IS NULL;
        SQL);

        $documento = json_decode(
            (string) file_get_contents(database_path('data/mgn2025_meta.geojson')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $geometria = json_encode($documento['features'][0]['geometry'], JSON_THROW_ON_ERROR);
        unset($documento);

        DB::insert(
            'insert into inteligencia.limite_referencia
                (codigo, nombre, cita_fuente, url_fuente, fecha_corte, geom)
             values (
                ?,
                ?,
                ?,
                ?,
                ?,
                ST_Multi(ST_CollectionExtract(ST_MakeValid(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 9377)), 3))::geometry(MultiPolygon, 9377)
             )',
            [
                'META',
                'Meta — límite departamental',
                'Departamento Administrativo Nacional de Estadística. Marco Geoestadístico Nacional versión 2025, nivel Departamento, Meta (DPTO_CCDGO 50). https://geoportal.dane.gov.co/mparcgis/rest/services/MGN2025/Serv_CapasMGN_2025/FeatureServer/319. Especificación técnica 2025-12-10: https://geoportal.dane.gov.co/descargas/mgn_2025/MGN2025_Especificacion_Tecnica.pdf. Copyright: Departamento Administrativo Nacional de Estadística DANE.',
                'https://geoportal.dane.gov.co/mparcgis/rest/services/MGN2025/Serv_CapasMGN_2025/FeatureServer/319',
                '2025-12-10 05:00:00+00',
                $geometria,
            ],
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.analisis_area
                DROP COLUMN IF EXISTS pct_fuera_meta,
                DROP COLUMN IF EXISTS ha_fuera_meta,
                DROP COLUMN IF EXISTS geometria_analizada,
                DROP COLUMN IF EXISTS geometria_original;

            DROP TABLE IF EXISTS inteligencia.limite_referencia;
        SQL);
    }
};
