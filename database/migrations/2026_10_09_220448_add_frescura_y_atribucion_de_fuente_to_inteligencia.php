<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Frescura de cada capa y atribución obligatoria en el resultado.
 * La sincronización guarda la fecha de corte de la fuente antes de decidir si descarga.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.capa
                ADD COLUMN frecuencia_actualizacion varchar(20) NOT NULL
                    CHECK (frecuencia_actualizacion IN ('diaria', 'semanal', 'mensual', 'por_version', 'tiempo_real')),
                ADD COLUMN ttl_horas integer NOT NULL CHECK (ttl_horas >= 0),
                ADD COLUMN fecha_corte_fuente timestamptz,
                ADD COLUMN fecha_ultima_sincronizacion timestamptz,
                ADD COLUMN estado_frescura varchar(40) NOT NULL DEFAULT 'posiblemente_desactualizada'
                    CHECK (estado_frescura IN ('vigente', 'posiblemente_desactualizada', 'error')),
                ADD COLUMN cita_fuente text NOT NULL;

            COMMENT ON COLUMN inteligencia.capa.ttl_horas IS
                'Edad máxima, en horas, de la última sincronización antes de marcar la copia como posiblemente desactualizada. Cero exige lectura en vivo.';
            COMMENT ON COLUMN inteligencia.capa.fecha_corte_fuente IS
                'Fecha de última edición que publica la fuente. La sincronización la compara antes de descargar.';

            CREATE TABLE inteligencia.capa_sincronizacion (
                id bigserial PRIMARY KEY,
                capa_id bigint NOT NULL REFERENCES inteligencia.capa (id) ON DELETE RESTRICT,
                iniciado_at timestamptz NOT NULL,
                finalizado_at timestamptz,
                fecha_corte_detectada timestamptz,
                cambio boolean NOT NULL,
                entidades_agregadas integer NOT NULL DEFAULT 0 CHECK (entidades_agregadas >= 0),
                entidades_actualizadas integer NOT NULL DEFAULT 0 CHECK (entidades_actualizadas >= 0),
                entidades_eliminadas integer NOT NULL DEFAULT 0 CHECK (entidades_eliminadas >= 0),
                estado varchar(20) NOT NULL CHECK (estado IN ('en_proceso', 'exitoso', 'sin_cambios', 'fallido')),
                mensaje_error text,
                created_at timestamptz,
                updated_at timestamptz,
                CONSTRAINT ck_sincronizacion_fechas CHECK (finalizado_at IS NULL OR finalizado_at >= iniciado_at),
                CONSTRAINT ck_sincronizacion_error CHECK (estado <> 'fallido' OR mensaje_error IS NOT NULL),
                CONSTRAINT ck_sincronizacion_sin_cambios CHECK (estado <> 'sin_cambios' OR cambio = false),
                CONSTRAINT ck_sincronizacion_descarga CHECK (
                    cambio
                    OR (
                        entidades_agregadas = 0
                        AND entidades_actualizadas = 0
                        AND entidades_eliminadas = 0
                    )
                ),
                CONSTRAINT capa_sincronizacion_id_capa_uq UNIQUE (id, capa_id)
            );

            CREATE INDEX capa_sincronizacion_capa_ix
                ON inteligencia.capa_sincronizacion (capa_id, iniciado_at DESC);

            COMMENT ON TABLE inteligencia.capa_sincronizacion IS
                'Cada corrida lee primero la fecha de corte de la fuente. Si no cambió, cambio queda en falso y no se registran entidades descargadas.';

            ALTER TABLE inteligencia.analisis_area
                ADD COLUMN modo varchar(20) NOT NULL DEFAULT 'normal'
                    CHECK (modo IN ('normal', 'oficial'));

            COMMENT ON COLUMN inteligencia.analisis_area.modo IS
                'oficial consulta la fuente directamente y solo usa la copia local si la fuente falla, dejando la advertencia en el resultado.';

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
                      AND rel.relname = 'analisis_resultado'
                      AND con.contype = 'c'
                      AND pg_get_constraintdef(con.oid) ILIKE '%servido_desde%'
                LOOP
                    EXECUTE format(
                        'ALTER TABLE inteligencia.analisis_resultado DROP CONSTRAINT %I',
                        restriccion.conname
                    );
                END LOOP;
            END $$;

            ALTER TABLE inteligencia.analisis_resultado
                ALTER COLUMN servido_desde TYPE varchar(40);

            ALTER TABLE inteligencia.analisis_resultado
                ADD CONSTRAINT ck_analisis_resultado_servido_desde
                    CHECK (servido_desde IN ('copia', 'fuente_directa', 'copia_por_falla_fuente')),
                ADD COLUMN cita_fuente text NOT NULL,
                ADD COLUMN url_fuente text NOT NULL,
                ADD COLUMN licencia text NOT NULL,
                ADD COLUMN fecha_corte timestamptz NOT NULL,
                ADD COLUMN obsoleto boolean NOT NULL,
                ADD COLUMN capa_sincronizacion_id bigint NOT NULL,
                ADD CONSTRAINT fk_resultado_sincronizacion_misma_capa
                    FOREIGN KEY (capa_sincronizacion_id, capa_id)
                    REFERENCES inteligencia.capa_sincronizacion (id, capa_id)
                    ON DELETE RESTRICT;

            COMMENT ON TABLE inteligencia.analisis_resultado IS
                'Un informe no puede guardarse sin la cita, la URL, la licencia, la fecha de corte y la sincronización usadas.';
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE inteligencia.analisis_resultado
                DROP CONSTRAINT IF EXISTS fk_resultado_sincronizacion_misma_capa,
                DROP CONSTRAINT IF EXISTS ck_analisis_resultado_servido_desde,
                DROP COLUMN IF EXISTS capa_sincronizacion_id,
                DROP COLUMN IF EXISTS obsoleto,
                DROP COLUMN IF EXISTS fecha_corte,
                DROP COLUMN IF EXISTS licencia,
                DROP COLUMN IF EXISTS url_fuente,
                DROP COLUMN IF EXISTS cita_fuente;

            UPDATE inteligencia.analisis_resultado
            SET servido_desde = 'cache'
            WHERE servido_desde NOT IN ('cache', 'upstream');

            ALTER TABLE inteligencia.analisis_resultado
                ALTER COLUMN servido_desde TYPE varchar(20),
                ADD CONSTRAINT analisis_resultado_servido_desde_check
                    CHECK (servido_desde IN ('cache', 'upstream'));

            ALTER TABLE inteligencia.analisis_area
                DROP COLUMN IF EXISTS modo;

            DROP TABLE IF EXISTS inteligencia.capa_sincronizacion;

            ALTER TABLE inteligencia.capa
                DROP COLUMN IF EXISTS cita_fuente,
                DROP COLUMN IF EXISTS estado_frescura,
                DROP COLUMN IF EXISTS fecha_ultima_sincronizacion,
                DROP COLUMN IF EXISTS fecha_corte_fuente,
                DROP COLUMN IF EXISTS ttl_horas,
                DROP COLUMN IF EXISTS frecuencia_actualizacion;
        SQL);
    }
};
