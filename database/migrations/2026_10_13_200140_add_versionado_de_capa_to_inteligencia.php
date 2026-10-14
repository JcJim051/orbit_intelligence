<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cada copia atribuible queda en una versión. Las entidades anteriores se archivan.
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
                ALTER COLUMN estado TYPE varchar(40);

            ALTER TABLE inteligencia.capa_sincronizacion
                DROP CONSTRAINT IF EXISTS ck_sincronizacion_estado;

            ALTER TABLE inteligencia.capa_sincronizacion
                ADD CONSTRAINT ck_sincronizacion_estado
                    CHECK (estado IN (
                        'en_proceso', 'exitoso', 'sin_cambios', 'republicado_sin_cambios', 'fallido', 'fuente_no_disponible'
                    ));

            ALTER TABLE inteligencia.capa_sincronizacion
                DROP CONSTRAINT IF EXISTS ck_sincronizacion_sin_cambios;

            ALTER TABLE inteligencia.capa_sincronizacion
                ADD CONSTRAINT ck_sincronizacion_sin_cambios
                    CHECK (estado NOT IN ('sin_cambios', 'republicado_sin_cambios') OR cambio = false);

            ALTER TABLE inteligencia.capa_sincronizacion
                ADD COLUMN huella_metadatos varchar(64);

            COMMENT ON COLUMN inteligencia.capa_sincronizacion.huella_metadatos IS
                'SHA-256 de la fecha de corte, la última edición y las cabeceras del paquete. Si coincide, no se descarga.';

            ALTER TABLE inteligencia.capa
                ADD COLUMN alerta text;

            COMMENT ON COLUMN inteligencia.capa.alerta IS
                'Aviso cuando la versión nueva mueve más del 20% de las entidades. El vencimiento del TTL se calcula al mostrar.';

            ALTER TABLE inteligencia.capa_objeto_cache
                ADD COLUMN valid_from integer NOT NULL DEFAULT 1,
                ADD COLUMN valid_to integer,
                ADD COLUMN checksum_entidad varchar(32);

            COMMENT ON COLUMN inteligencia.capa_objeto_cache.valid_from IS
                'Número de versión desde el cual la entidad entra en la copia.';
            COMMENT ON COLUMN inteligencia.capa_objeto_cache.valid_to IS
                'Número de versión que la reemplaza. Nulo mientras sigue vigente.';

            DROP INDEX IF EXISTS capa_objeto_cache_origen_uq;
            DROP INDEX IF EXISTS inteligencia.capa_objeto_cache_origen_uq;

            CREATE UNIQUE INDEX capa_objeto_cache_origen_uq
                ON inteligencia.capa_objeto_cache (capa_id, identificador_origen)
                WHERE identificador_origen IS NOT NULL AND valid_to IS NULL;

            CREATE INDEX capa_objeto_cache_version_ix
                ON inteligencia.capa_objeto_cache (capa_id, valid_from, valid_to);

            CREATE TABLE inteligencia.capa_version (
                id bigserial PRIMARY KEY,
                capa_id bigint NOT NULL REFERENCES inteligencia.capa (id) ON DELETE RESTRICT,
                numero integer NOT NULL CHECK (numero > 0),
                fecha_corte timestamptz,
                cita_fuente text NOT NULL,
                url_fuente text NOT NULL,
                licencia text NOT NULL,
                checksum varchar(64) NOT NULL,
                conteo integer NOT NULL CHECK (conteo >= 0),
                agregados integer NOT NULL DEFAULT 0 CHECK (agregados >= 0),
                modificados integer NOT NULL DEFAULT 0 CHECK (modificados >= 0),
                eliminados integer NOT NULL DEFAULT 0 CHECK (eliminados >= 0),
                vigente boolean NOT NULL DEFAULT false,
                sincronizacion_id bigint NOT NULL,
                created_at timestamptz,
                updated_at timestamptz,
                CONSTRAINT capa_version_numero_uq UNIQUE (capa_id, numero),
                CONSTRAINT capa_version_id_capa_uq UNIQUE (id, capa_id),
                CONSTRAINT fk_version_sincronizacion_misma_capa
                    FOREIGN KEY (sincronizacion_id, capa_id)
                    REFERENCES inteligencia.capa_sincronizacion (id, capa_id)
                    ON DELETE RESTRICT
            );

            CREATE UNIQUE INDEX capa_version_vigente_uq
                ON inteligencia.capa_version (capa_id)
                WHERE vigente;

            COMMENT ON TABLE inteligencia.capa_version IS
                'Instantánea atribuible de una capa. La vigente es la copia actual; las anteriores quedan archivadas.';

            INSERT INTO inteligencia.capa_version (
                capa_id, numero, fecha_corte, cita_fuente, url_fuente, licencia, checksum,
                conteo, agregados, modificados, eliminados, vigente, sincronizacion_id, created_at, updated_at
            )
            SELECT
                c.id,
                1,
                c.fecha_corte_fuente,
                coalesce(c.cita_fuente, ''),
                coalesce(c.url_servicio, ''),
                coalesce(c.licencia, ''),
                coalesce(nullif(s.checksum, ''), encode(sha256(convert_to(coalesce((
                    SELECT string_agg(
                        o.identificador_origen || '|' || md5(ST_AsEWKB(o.geom)) || '|' || md5(o.atributos::text),
                        E'\n' ORDER BY o.identificador_origen
                    )
                    FROM inteligencia.capa_objeto_cache o
                    WHERE o.capa_id = c.id AND o.valid_to IS NULL
                ), ''), 'UTF8')), 'hex')),
                (SELECT count(*)::int FROM inteligencia.capa_objeto_cache o WHERE o.capa_id = c.id AND o.valid_to IS NULL),
                (SELECT count(*)::int FROM inteligencia.capa_objeto_cache o WHERE o.capa_id = c.id AND o.valid_to IS NULL),
                0,
                0,
                true,
                s.id,
                now(),
                now()
            FROM inteligencia.capa c
            JOIN LATERAL (
                SELECT id, checksum
                FROM inteligencia.capa_sincronizacion
                WHERE capa_id = c.id
                  AND estado IN ('exitoso', 'sin_cambios', 'republicado_sin_cambios')
                ORDER BY finalizado_at DESC NULLS LAST
                LIMIT 1
            ) s ON true
            WHERE EXISTS (
                SELECT 1 FROM inteligencia.capa_objeto_cache o
                WHERE o.capa_id = c.id AND o.valid_to IS NULL
            );

            ALTER TABLE inteligencia.analisis_resultado
                ADD COLUMN capa_version_id bigint;

            UPDATE inteligencia.analisis_resultado r
            SET capa_version_id = v.id
            FROM inteligencia.capa_version v
            WHERE v.capa_id = r.capa_id
              AND v.vigente = true
              AND r.capa_version_id IS NULL;

            DELETE FROM inteligencia.analisis_resultado
            WHERE capa_version_id IS NULL;

            ALTER TABLE inteligencia.analisis_resultado
                ALTER COLUMN capa_version_id SET NOT NULL,
                ADD CONSTRAINT fk_resultado_version_misma_capa
                    FOREIGN KEY (capa_version_id, capa_id)
                    REFERENCES inteligencia.capa_version (id, capa_id)
                    ON DELETE RESTRICT;

            COMMENT ON COLUMN inteligencia.analisis_resultado.capa_version_id IS
                'Versión exacta usada en el informe. El reporte no se reescribe cuando llega una versión nueva.';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.analisis_resultado
                DROP CONSTRAINT IF EXISTS fk_resultado_version_misma_capa,
                DROP COLUMN IF EXISTS capa_version_id;

            DROP TABLE IF EXISTS inteligencia.capa_version;

            DELETE FROM inteligencia.capa_objeto_cache
            WHERE valid_to IS NOT NULL;

            DROP INDEX IF EXISTS capa_objeto_cache_version_ix;
            DROP INDEX IF EXISTS inteligencia.capa_objeto_cache_version_ix;
            DROP INDEX IF EXISTS capa_objeto_cache_origen_uq;
            DROP INDEX IF EXISTS inteligencia.capa_objeto_cache_origen_uq;

            ALTER TABLE inteligencia.capa_objeto_cache
                DROP COLUMN IF EXISTS checksum_entidad,
                DROP COLUMN IF EXISTS valid_to,
                DROP COLUMN IF EXISTS valid_from;

            CREATE UNIQUE INDEX capa_objeto_cache_origen_uq
                ON inteligencia.capa_objeto_cache (capa_id, identificador_origen)
                WHERE identificador_origen IS NOT NULL;

            ALTER TABLE inteligencia.capa
                DROP COLUMN IF EXISTS alerta;

            ALTER TABLE inteligencia.capa_sincronizacion
                DROP COLUMN IF EXISTS huella_metadatos;

            UPDATE inteligencia.capa_sincronizacion
            SET estado = 'sin_cambios'
            WHERE estado = 'republicado_sin_cambios';

            ALTER TABLE inteligencia.capa_sincronizacion
                DROP CONSTRAINT IF EXISTS ck_sincronizacion_estado,
                DROP CONSTRAINT IF EXISTS ck_sincronizacion_sin_cambios;

            ALTER TABLE inteligencia.capa_sincronizacion
                ADD CONSTRAINT ck_sincronizacion_estado
                    CHECK (estado IN ('en_proceso', 'exitoso', 'sin_cambios', 'fallido', 'fuente_no_disponible')),
                ADD CONSTRAINT ck_sincronizacion_sin_cambios
                    CHECK (estado <> 'sin_cambios' OR cambio = false);
        SQL);
    }
};
