<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La bitácora de cada corrida guarda la URL, la licencia y el checksum leídos de la fuente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.capa_sincronizacion
                ADD COLUMN url_consultada text,
                ADD COLUMN licencia_detectada text,
                ADD COLUMN checksum varchar(64),
                ADD COLUMN nota text;

            COMMENT ON COLUMN inteligencia.capa_sincronizacion.url_consultada IS
                'URL del servicio o del cargue usado en la corrida.';
            COMMENT ON COLUMN inteligencia.capa_sincronizacion.licencia_detectada IS
                'Licencia publicada por la fuente. Si el servicio no la declara, queda dicho de forma explícita.';
            COMMENT ON COLUMN inteligencia.capa_sincronizacion.checksum IS
                'SHA-256 de los identificadores y las geometrías cargadas, para detectar cambios.';

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
                      AND rel.relname = 'capa_sincronizacion'
                      AND con.contype = 'c'
                      AND pg_get_constraintdef(con.oid) ILIKE '%en_proceso%'
                LOOP
                    EXECUTE format(
                        'ALTER TABLE inteligencia.capa_sincronizacion DROP CONSTRAINT %I',
                        restriccion.conname
                    );
                END LOOP;
            END $$;

            ALTER TABLE inteligencia.capa_sincronizacion
                ADD CONSTRAINT ck_sincronizacion_estado
                    CHECK (estado IN ('en_proceso', 'exitoso', 'sin_cambios', 'fallido', 'fuente_no_disponible')),
                ADD CONSTRAINT ck_sincronizacion_no_disponible
                    CHECK (estado <> 'fuente_no_disponible' OR mensaje_error IS NOT NULL);

            CREATE OR REPLACE FUNCTION inteligencia.geom_desde_geojson(texto text, srid integer)
            RETURNS geometry
            LANGUAGE plpgsql
            SET search_path = public, inteligencia
            AS $$
            DECLARE
                resultado geometry;
            BEGIN
                IF texto IS NULL OR btrim(texto) = '' OR srid IS NULL THEN
                    RETURN NULL;
                END IF;

                resultado := ST_MakeValid(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(texto), srid), 9377));

                IF resultado IS NULL OR ST_IsEmpty(resultado) THEN
                    RETURN NULL;
                END IF;

                RETURN resultado;
            EXCEPTION WHEN OTHERS THEN
                RETURN NULL;
            END;
            $$;
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP FUNCTION IF EXISTS inteligencia.geom_desde_geojson(text, integer);

            ALTER TABLE inteligencia.capa_sincronizacion
                DROP CONSTRAINT IF EXISTS ck_sincronizacion_no_disponible,
                DROP CONSTRAINT IF EXISTS ck_sincronizacion_estado;

            DELETE FROM inteligencia.capa_sincronizacion
            WHERE estado = 'fuente_no_disponible';

            ALTER TABLE inteligencia.capa_sincronizacion
                ADD CONSTRAINT ck_sincronizacion_estado
                    CHECK (estado IN ('en_proceso', 'exitoso', 'sin_cambios', 'fallido'));

            ALTER TABLE inteligencia.capa_sincronizacion
                DROP COLUMN IF EXISTS nota,
                DROP COLUMN IF EXISTS checksum,
                DROP COLUMN IF EXISTS licencia_detectada,
                DROP COLUMN IF EXISTS url_consultada;
        SQL);
    }
};
