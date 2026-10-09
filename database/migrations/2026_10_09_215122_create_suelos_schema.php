<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Esquema suelos (estudios geotécnicos georreferenciados).
 *
 * No crea public.proyecto, public.contrato_secop, public.users, suelos.municipio
 * ni suelos.entidad: reutiliza investment_projects, investment_contracts, users,
 * municipios y dependencias. Los límites municipales van en suelos.limite_municipio
 * porque municipios no guarda geometría.
 *
 * En SQLite la migración no hace nada. Aplicar en PostGIS con:
 * php artisan migrate --database=managed_postgis_admin
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE EXTENSION IF NOT EXISTS postgis;
            CREATE EXTENSION IF NOT EXISTS btree_gist;

            INSERT INTO spatial_ref_sys (srid, auth_name, auth_srid, srtext, proj4text)
            SELECT 9377, 'EPSG', 9377,
                   'PROJCS["MAGNA-SIRGAS / Origen-Nacional",GEOGCS["MAGNA-SIRGAS",DATUM["Marco_Geocentrico_Nacional_de_Referencia",SPHEROID["GRS 1980",6378137,298.257222101]],PRIMEM["Greenwich",0],UNIT["degree",0.0174532925199433]],PROJECTION["Transverse_Mercator"],PARAMETER["latitude_of_origin",4],PARAMETER["central_meridian",-73],PARAMETER["scale_factor",0.9992],PARAMETER["false_easting",5000000],PARAMETER["false_northing",2000000],UNIT["metre",1]]',
                   '+proj=tmerc +lat_0=4 +lon_0=-73 +k=0.9992 +x_0=5000000 +y_0=2000000 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs'
            WHERE NOT EXISTS (SELECT 1 FROM spatial_ref_sys WHERE srid = 9377);

            CREATE UNIQUE INDEX IF NOT EXISTS investment_contracts_id_project_uq
                ON public.investment_contracts (id, investment_project_id);

            DROP SCHEMA IF EXISTS suelos CASCADE;
            CREATE SCHEMA suelos;

            CREATE TABLE suelos.tipo_exploracion (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                descripcion text,
                orden smallint NOT NULL DEFAULT 0,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.clasificacion_uscs (
                id smallserial PRIMARY KEY,
                codigo varchar(10) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                grupo varchar(20) NOT NULL CHECK (grupo IN ('GRUESO', 'FINO', 'ORGANICO', 'DOBLE')),
                descripcion text,
                orden smallint NOT NULL DEFAULT 0,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.clasificacion_aashto (
                id smallserial PRIMARY KEY,
                codigo varchar(10) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                descripcion text,
                orden smallint NOT NULL DEFAULT 0,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.unidad_medida (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(100) NOT NULL,
                simbolo varchar(20) NOT NULL,
                magnitud varchar(50) NOT NULL,
                factor_a_base numeric(18, 9),
                unidad_base_id smallint REFERENCES suelos.unidad_medida (id) ON DELETE RESTRICT,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.tipo_ensayo (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                ambito varchar(15) NOT NULL CHECK (ambito IN ('LABORATORIO', 'CAMPO')),
                unidad_id smallint NOT NULL REFERENCES suelos.unidad_medida (id) ON DELETE RESTRICT,
                rango_min numeric,
                rango_max numeric,
                norma_referencia varchar(120),
                descripcion text,
                orden smallint NOT NULL DEFAULT 0,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT ck_tipo_ensayo_rango CHECK (rango_min IS NULL OR rango_max IS NULL OR rango_min < rango_max)
            );

            CREATE TABLE suelos.perfil_suelo_nsr10 (
                id smallserial PRIMARY KEY,
                codigo char(1) NOT NULL UNIQUE CHECK (codigo IN ('A', 'B', 'C', 'D', 'E', 'F')),
                nombre varchar(150) NOT NULL,
                vs30_min_m_s numeric,
                vs30_max_m_s numeric,
                descripcion text,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.metodo_coordenadas (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                precision_estimada_m numeric(8, 2),
                descripcion text,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.estado_validacion (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(100) NOT NULL,
                es_final boolean NOT NULL DEFAULT false,
                visible_en_informe boolean NOT NULL DEFAULT false,
                orden smallint NOT NULL DEFAULT 0,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.tipo_cimentacion (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                descripcion text,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.tipo_adjunto (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                extensiones_permitidas varchar(100) NOT NULL DEFAULT 'pdf',
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.tipo_estudio (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.sistema_coordenadas (
                id smallserial PRIMARY KEY,
                codigo varchar(20) NOT NULL UNIQUE,
                nombre varchar(150) NOT NULL,
                srid integer NOT NULL UNIQUE,
                observacion text,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE suelos.limite_municipio (
                municipio_id bigint PRIMARY KEY REFERENCES public.municipios (id) ON DELETE CASCADE,
                geom geometry(MultiPolygon, 9377) NOT NULL
            );

            CREATE INDEX limite_municipio_geom_gix ON suelos.limite_municipio USING gist (geom);

            CREATE TABLE suelos.estudio_suelos (
                id bigserial PRIMARY KEY,
                codigo varchar(30) NOT NULL UNIQUE,
                titulo text NOT NULL,
                tipo_estudio_id smallint NOT NULL REFERENCES suelos.tipo_estudio (id) ON DELETE RESTRICT,
                dependencia_id bigint NOT NULL REFERENCES public.dependencias (id) ON DELETE RESTRICT,
                investment_project_id char(26) REFERENCES public.investment_projects (id) ON DELETE RESTRICT,
                investment_contract_id char(26),
                sin_bpin_justificacion text,
                consultor_nombre varchar(200) NOT NULL,
                consultor_nit varchar(15),
                ingeniero_responsable varchar(200),
                matricula_profesional varchar(40),
                fecha_estudio date NOT NULL,
                fecha_exploracion_ini date,
                fecha_exploracion_fin date,
                municipio_id bigint NOT NULL REFERENCES public.municipios (id) ON DELETE RESTRICT,
                descripcion_sitio text,
                estado_validacion_id smallint NOT NULL REFERENCES suelos.estado_validacion (id) ON DELETE RESTRICT,
                revisado_por bigint REFERENCES public.users (id) ON DELETE RESTRICT,
                fecha_revision timestamptz,
                observacion_revision text,
                cargado_por bigint NOT NULL REFERENCES public.users (id) ON DELETE RESTRICT,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                deleted_at timestamptz,
                CONSTRAINT fk_estudio_contrato_mismo_proyecto FOREIGN KEY (investment_contract_id, investment_project_id)
                    REFERENCES public.investment_contracts (id, investment_project_id),
                CONSTRAINT ck_estudio_bpin_o_justificacion CHECK (
                    investment_project_id IS NOT NULL
                    OR length(trim(coalesce(sin_bpin_justificacion, ''))) >= 20
                ),
                CONSTRAINT ck_estudio_contrato_requiere_proyecto CHECK (
                    investment_contract_id IS NULL OR investment_project_id IS NOT NULL
                ),
                CONSTRAINT ck_estudio_fechas CHECK (
                    (fecha_exploracion_ini IS NULL OR fecha_exploracion_fin IS NULL OR fecha_exploracion_ini <= fecha_exploracion_fin)
                    AND fecha_estudio <= current_date + 1
                    AND fecha_estudio >= DATE '1950-01-01'
                )
            );

            CREATE INDEX estudio_proyecto_ix ON suelos.estudio_suelos (investment_project_id);
            CREATE INDEX estudio_dependencia_ix ON suelos.estudio_suelos (dependencia_id);
            CREATE INDEX estudio_municipio_ix ON suelos.estudio_suelos (municipio_id);
            CREATE INDEX estudio_estado_ix ON suelos.estudio_suelos (estado_validacion_id);
            CREATE UNIQUE INDEX estudio_dup_uq ON suelos.estudio_suelos (
                coalesce(investment_project_id::text, '-'),
                coalesce(investment_contract_id::text, '-'),
                lower(consultor_nombre),
                fecha_estudio
            ) WHERE deleted_at IS NULL;

            CREATE TABLE suelos.exploracion (
                id bigserial PRIMARY KEY,
                estudio_id bigint NOT NULL REFERENCES suelos.estudio_suelos (id) ON DELETE CASCADE,
                codigo varchar(30) NOT NULL,
                tipo_exploracion_id smallint NOT NULL REFERENCES suelos.tipo_exploracion (id) ON DELETE RESTRICT,
                x_original numeric(14, 4) NOT NULL,
                y_original numeric(14, 4) NOT NULL,
                sistema_coordenadas_id smallint NOT NULL REFERENCES suelos.sistema_coordenadas (id) ON DELETE RESTRICT,
                metodo_coordenadas_id smallint NOT NULL REFERENCES suelos.metodo_coordenadas (id) ON DELETE RESTRICT,
                cota_msnm numeric(8, 2),
                geom geometry(PointZ, 9377) NOT NULL,
                profundidad_total_m numeric(7, 2) NOT NULL CHECK (profundidad_total_m > 0 AND profundidad_total_m <= 200),
                nivel_freatico_m numeric(7, 2) CHECK (nivel_freatico_m >= 0),
                nivel_freatico_encontrado boolean NOT NULL,
                fecha_nivel_freatico date,
                fecha_ejecucion date,
                metodo_perforacion varchar(150),
                rechazo boolean NOT NULL DEFAULT false,
                observaciones text,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT uq_exploracion_codigo UNIQUE (estudio_id, codigo),
                CONSTRAINT ck_nf_coherente CHECK (
                    (nivel_freatico_encontrado AND nivel_freatico_m IS NOT NULL AND nivel_freatico_m <= profundidad_total_m)
                    OR (NOT nivel_freatico_encontrado AND nivel_freatico_m IS NULL)
                ),
                CONSTRAINT ck_cota_rango CHECK (cota_msnm IS NULL OR cota_msnm BETWEEN 0 AND 5000)
            );

            CREATE INDEX exploracion_geom_gix ON suelos.exploracion USING gist (geom);
            CREATE INDEX exploracion_estudio_ix ON suelos.exploracion (estudio_id);
            CREATE INDEX exploracion_tipo_ix ON suelos.exploracion (tipo_exploracion_id);

            CREATE TABLE suelos.estrato (
                id bigserial PRIMARY KEY,
                exploracion_id bigint NOT NULL REFERENCES suelos.exploracion (id) ON DELETE CASCADE,
                profundidad_desde_m numeric(7, 2) NOT NULL CHECK (profundidad_desde_m >= 0),
                profundidad_hasta_m numeric(7, 2) NOT NULL,
                clasificacion_uscs_id smallint REFERENCES suelos.clasificacion_uscs (id) ON DELETE RESTRICT,
                clasificacion_aashto_id smallint REFERENCES suelos.clasificacion_aashto (id) ON DELETE RESTRICT,
                descripcion text NOT NULL,
                color varchar(80),
                humedad_visual varchar(30) CHECK (humedad_visual IN ('SECO', 'HUMEDO', 'MUY_HUMEDO', 'SATURADO')),
                consistencia varchar(30) CHECK (consistencia IN ('MUY_BLANDA', 'BLANDA', 'MEDIA', 'FIRME', 'MUY_FIRME', 'DURA')),
                compacidad varchar(30) CHECK (compacidad IN ('MUY_SUELTA', 'SUELTA', 'MEDIA', 'DENSA', 'MUY_DENSA')),
                es_relleno boolean NOT NULL DEFAULT false,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT ck_estrato_profundidad CHECK (profundidad_hasta_m > profundidad_desde_m),
                CONSTRAINT ex_estrato_sin_traslape EXCLUDE USING gist (
                    exploracion_id WITH =,
                    numrange(profundidad_desde_m, profundidad_hasta_m, '[)') WITH &&
                )
            );

            CREATE INDEX estrato_exploracion_ix ON suelos.estrato (exploracion_id);
            CREATE INDEX estrato_uscs_ix ON suelos.estrato (clasificacion_uscs_id);

            CREATE TABLE suelos.ensayo (
                id bigserial PRIMARY KEY,
                exploracion_id bigint NOT NULL REFERENCES suelos.exploracion (id) ON DELETE CASCADE,
                estrato_id bigint REFERENCES suelos.estrato (id) ON DELETE SET NULL,
                muestra varchar(30),
                profundidad_desde_m numeric(7, 2) NOT NULL CHECK (profundidad_desde_m >= 0),
                profundidad_hasta_m numeric(7, 2),
                tipo_ensayo_id smallint NOT NULL REFERENCES suelos.tipo_ensayo (id) ON DELETE RESTRICT,
                valor numeric(14, 4) NOT NULL,
                valor_original numeric(14, 4),
                unidad_original_id smallint REFERENCES suelos.unidad_medida (id) ON DELETE RESTRICT,
                es_rechazo boolean NOT NULL DEFAULT false,
                observacion text,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT ck_ensayo_profundidad CHECK (profundidad_hasta_m IS NULL OR profundidad_hasta_m >= profundidad_desde_m),
                CONSTRAINT uq_ensayo UNIQUE NULLS NOT DISTINCT (exploracion_id, tipo_ensayo_id, profundidad_desde_m, muestra)
            );

            CREATE INDEX ensayo_exploracion_ix ON suelos.ensayo (exploracion_id);
            CREATE INDEX ensayo_tipo_ix ON suelos.ensayo (tipo_ensayo_id);

            CREATE TABLE suelos.parametros_diseno (
                id bigserial PRIMARY KEY,
                estudio_id bigint NOT NULL REFERENCES suelos.estudio_suelos (id) ON DELETE CASCADE,
                exploracion_id bigint REFERENCES suelos.exploracion (id) ON DELETE CASCADE,
                perfil_suelo_nsr10_id smallint REFERENCES suelos.perfil_suelo_nsr10 (id) ON DELETE RESTRICT,
                vs30_m_s numeric(7, 1) CHECK (vs30_m_s IS NULL OR vs30_m_s BETWEEN 50 AND 3000),
                capacidad_portante_adm_kpa numeric(9, 2) CHECK (capacidad_portante_adm_kpa IS NULL OR capacidad_portante_adm_kpa > 0),
                profundidad_desplante_m numeric(6, 2) CHECK (profundidad_desplante_m IS NULL OR profundidad_desplante_m >= 0),
                tipo_cimentacion_id smallint REFERENCES suelos.tipo_cimentacion (id) ON DELETE RESTRICT,
                asentamiento_estimado_cm numeric(7, 2),
                cbr_diseno_pct numeric(6, 2) CHECK (cbr_diseno_pct IS NULL OR cbr_diseno_pct BETWEEN 0 AND 100),
                potencial_expansivo varchar(20) CHECK (potencial_expansivo IN ('BAJO', 'MEDIO', 'ALTO', 'MUY_ALTO')),
                potencial_licuacion varchar(20) CHECK (potencial_licuacion IN ('NO_EVALUADO', 'BAJO', 'MEDIO', 'ALTO')),
                recomendaciones text,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE INDEX parametros_estudio_ix ON suelos.parametros_diseno (estudio_id);
            CREATE UNIQUE INDEX parametros_uq ON suelos.parametros_diseno (estudio_id, coalesce(exploracion_id, 0));

            CREATE TABLE suelos.adjunto (
                id bigserial PRIMARY KEY,
                estudio_id bigint NOT NULL REFERENCES suelos.estudio_suelos (id) ON DELETE CASCADE,
                tipo_adjunto_id smallint NOT NULL REFERENCES suelos.tipo_adjunto (id) ON DELETE RESTRICT,
                nombre_archivo varchar(255) NOT NULL,
                ruta_storage text NOT NULL,
                mime_type varchar(100) NOT NULL,
                tamano_bytes bigint NOT NULL CHECK (tamano_bytes > 0),
                sha256 char(64) NOT NULL,
                cargado_por bigint NOT NULL REFERENCES public.users (id) ON DELETE RESTRICT,
                created_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT uq_adjunto_hash UNIQUE (estudio_id, sha256)
            );

            CREATE INDEX adjunto_sha_ix ON suelos.adjunto (sha256);

            CREATE TABLE suelos.estudio_validacion_historial (
                id bigserial PRIMARY KEY,
                estudio_id bigint NOT NULL REFERENCES suelos.estudio_suelos (id) ON DELETE CASCADE,
                estado_anterior_id smallint REFERENCES suelos.estado_validacion (id) ON DELETE RESTRICT,
                estado_nuevo_id smallint NOT NULL REFERENCES suelos.estado_validacion (id) ON DELETE RESTRICT,
                usuario_id bigint NOT NULL REFERENCES public.users (id) ON DELETE RESTRICT,
                observacion text,
                created_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE INDEX historial_estudio_ix ON suelos.estudio_validacion_historial (estudio_id);

            CREATE OR REPLACE FUNCTION suelos.fn_exploracion_geom() RETURNS trigger
            LANGUAGE plpgsql AS $fn$
            DECLARE
                v_srid integer;
                v_pt geometry;
                v_meta geometry;
            BEGIN
                SELECT srid INTO v_srid FROM suelos.sistema_coordenadas WHERE id = NEW.sistema_coordenadas_id;
                IF v_srid IS NULL THEN
                    RAISE EXCEPTION 'Sistema de coordenadas no declarado o inválido';
                END IF;

                v_pt := ST_Transform(ST_SetSRID(ST_MakePoint(NEW.x_original, NEW.y_original), v_srid), 9377);
                NEW.geom := ST_SetSRID(ST_MakePoint(ST_X(v_pt), ST_Y(v_pt), coalesce(NEW.cota_msnm, 0)), 9377);

                SELECT ST_Union(geom) INTO v_meta FROM suelos.limite_municipio;
                IF v_meta IS NOT NULL AND NOT ST_DWithin(v_meta, ST_Force2D(NEW.geom), 1000) THEN
                    RAISE EXCEPTION 'La exploración % cae fuera del departamento del Meta. Revise coordenadas y sistema declarado.', NEW.codigo;
                END IF;

                NEW.updated_at := now();
                RETURN NEW;
            END
            $fn$;

            CREATE TRIGGER trg_exploracion_geom
                BEFORE INSERT OR UPDATE OF x_original, y_original, sistema_coordenadas_id, cota_msnm
                ON suelos.exploracion
                FOR EACH ROW EXECUTE FUNCTION suelos.fn_exploracion_geom();

            CREATE OR REPLACE FUNCTION suelos.fn_valida_profundidad() RETURNS trigger
            LANGUAGE plpgsql AS $fn$
            DECLARE
                v_prof numeric;
            BEGIN
                SELECT profundidad_total_m INTO v_prof FROM suelos.exploracion WHERE id = NEW.exploracion_id;
                IF coalesce(NEW.profundidad_hasta_m, NEW.profundidad_desde_m) > v_prof + 0.05 THEN
                    RAISE EXCEPTION 'Profundidad (% m) mayor que la profundidad total de la exploración (% m)',
                        coalesce(NEW.profundidad_hasta_m, NEW.profundidad_desde_m), v_prof;
                END IF;
                RETURN NEW;
            END
            $fn$;

            CREATE TRIGGER trg_estrato_prof
                BEFORE INSERT OR UPDATE ON suelos.estrato
                FOR EACH ROW EXECUTE FUNCTION suelos.fn_valida_profundidad();

            CREATE TRIGGER trg_ensayo_prof
                BEFORE INSERT OR UPDATE ON suelos.ensayo
                FOR EACH ROW EXECUTE FUNCTION suelos.fn_valida_profundidad();

            CREATE OR REPLACE VIEW suelos.v_exploraciones_resumen AS
            WITH uscs_espesor AS (
                SELECT e.exploracion_id, u.codigo, u.nombre,
                       sum(e.profundidad_hasta_m - e.profundidad_desde_m) AS espesor_m,
                       row_number() OVER (
                           PARTITION BY e.exploracion_id
                           ORDER BY sum(e.profundidad_hasta_m - e.profundidad_desde_m) DESC
                       ) AS rn
                FROM suelos.estrato e
                JOIN suelos.clasificacion_uscs u ON u.id = e.clasificacion_uscs_id
                GROUP BY e.exploracion_id, u.codigo, u.nombre
            ),
            uscs_sup AS (
                SELECT DISTINCT ON (e.exploracion_id) e.exploracion_id, u.codigo
                FROM suelos.estrato e
                JOIN suelos.clasificacion_uscs u ON u.id = e.clasificacion_uscs_id
                WHERE NOT e.es_relleno
                ORDER BY e.exploracion_id, e.profundidad_desde_m
            ),
            spt AS (
                SELECT en.exploracion_id, min(en.valor) AS spt_n_min, max(en.valor) AS spt_n_max, count(*) AS n_spt
                FROM suelos.ensayo en
                JOIN suelos.tipo_ensayo t ON t.id = en.tipo_ensayo_id AND t.codigo = 'SPT_N'
                GROUP BY en.exploracion_id
            )
            SELECT
                x.id AS exploracion_id,
                x.codigo AS exploracion,
                te.codigo || ' — ' || te.nombre AS tipo_exploracion,
                es.id AS estudio_id,
                es.codigo AS estudio,
                d.codigo || ' — ' || d.nombre AS entidad,
                p.bpin,
                m.codigo_dane || ' — ' || m.nombre AS municipio,
                es.fecha_estudio,
                x.cota_msnm,
                x.profundidad_total_m,
                x.nivel_freatico_encontrado,
                x.nivel_freatico_m,
                ud.codigo AS uscs_predominante,
                ud.espesor_m AS uscs_predominante_espesor_m,
                us.codigo AS uscs_superficial,
                spt.n_spt,
                spt.spt_n_min,
                spt.spt_n_max,
                pf.codigo AS perfil_nsr10,
                pd.capacidad_portante_adm_kpa,
                mc.codigo || ' — ' || mc.nombre AS metodo_coordenadas,
                mc.precision_estimada_m,
                ev.codigo AS estado_validacion,
                ev.visible_en_informe,
                x.geom AS geom
            FROM suelos.exploracion x
            JOIN suelos.estudio_suelos es ON es.id = x.estudio_id AND es.deleted_at IS NULL
            JOIN suelos.tipo_exploracion te ON te.id = x.tipo_exploracion_id
            JOIN public.dependencias d ON d.id = es.dependencia_id
            JOIN public.municipios m ON m.id = es.municipio_id
            JOIN suelos.metodo_coordenadas mc ON mc.id = x.metodo_coordenadas_id
            JOIN suelos.estado_validacion ev ON ev.id = es.estado_validacion_id
            LEFT JOIN public.investment_projects p ON p.id = es.investment_project_id
            LEFT JOIN uscs_espesor ud ON ud.exploracion_id = x.id AND ud.rn = 1
            LEFT JOIN uscs_sup us ON us.exploracion_id = x.id
            LEFT JOIN spt ON spt.exploracion_id = x.id
            LEFT JOIN LATERAL (
                SELECT *
                FROM suelos.parametros_diseno q
                WHERE q.estudio_id = es.id
                  AND (q.exploracion_id = x.id OR q.exploracion_id IS NULL)
                ORDER BY q.exploracion_id NULLS LAST
                LIMIT 1
            ) pd ON true
            LEFT JOIN suelos.perfil_suelo_nsr10 pf ON pf.id = pd.perfil_suelo_nsr10_id;

            CREATE OR REPLACE VIEW suelos.v_exploraciones_publicas_4326 AS
            SELECT exploracion_id, exploracion, tipo_exploracion, estudio, entidad, bpin, municipio, fecha_estudio,
                   profundidad_total_m, nivel_freatico_m, uscs_predominante, uscs_superficial, perfil_nsr10,
                   ST_Transform(ST_Force2D(geom), 4326)::geometry(Point, 4326) AS geom
            FROM suelos.v_exploraciones_resumen
            WHERE visible_en_informe;

            CREATE OR REPLACE VIEW suelos.v_alertas_calidad AS
            SELECT es.id AS estudio_id, x.id AS exploracion_id, 'ENSAYO_FUERA_DE_RANGO'::text AS alerta,
                   t.codigo || ' = ' || en.valor || ' a ' || en.profundidad_desde_m || ' m' AS detalle
            FROM suelos.ensayo en
            JOIN suelos.tipo_ensayo t ON t.id = en.tipo_ensayo_id
            JOIN suelos.exploracion x ON x.id = en.exploracion_id
            JOIN suelos.estudio_suelos es ON es.id = x.estudio_id
            WHERE (t.rango_min IS NOT NULL AND en.valor < t.rango_min)
               OR (t.rango_max IS NOT NULL AND en.valor > t.rango_max)
            UNION ALL
            SELECT es.id, x.id, 'IP_DISTINTO_LL_MENOS_LP',
                   'LL=' || ll.valor || ' LP=' || lp.valor || ' IP=' || ip.valor
            FROM suelos.ensayo ll
            JOIN suelos.tipo_ensayo tll ON tll.id = ll.tipo_ensayo_id AND tll.codigo = 'LL'
            JOIN suelos.ensayo lp ON lp.exploracion_id = ll.exploracion_id AND lp.profundidad_desde_m = ll.profundidad_desde_m
            JOIN suelos.tipo_ensayo tlp ON tlp.id = lp.tipo_ensayo_id AND tlp.codigo = 'LP'
            JOIN suelos.ensayo ip ON ip.exploracion_id = ll.exploracion_id AND ip.profundidad_desde_m = ll.profundidad_desde_m
            JOIN suelos.tipo_ensayo tip ON tip.id = ip.tipo_ensayo_id AND tip.codigo = 'IP'
            JOIN suelos.exploracion x ON x.id = ll.exploracion_id
            JOIN suelos.estudio_suelos es ON es.id = x.estudio_id
            WHERE abs(ip.valor - (ll.valor - lp.valor)) > 1
            UNION ALL
            SELECT es.id, x.id, 'FUERA_DEL_MUNICIPIO_DECLARADO', m.codigo_dane || ' — ' || m.nombre
            FROM suelos.exploracion x
            JOIN suelos.estudio_suelos es ON es.id = x.estudio_id
            JOIN public.municipios m ON m.id = es.municipio_id
            JOIN suelos.limite_municipio lm ON lm.municipio_id = m.id
            WHERE NOT ST_DWithin(lm.geom, ST_Force2D(x.geom), 500)
            UNION ALL
            SELECT es.id, x.id, 'SIN_ESTRATOS', 'La exploración no tiene estratos descritos'
            FROM suelos.exploracion x
            JOIN suelos.estudio_suelos es ON es.id = x.estudio_id
            WHERE NOT EXISTS (SELECT 1 FROM suelos.estrato e WHERE e.exploracion_id = x.id)
              AND x.tipo_exploracion_id NOT IN (SELECT id FROM suelos.tipo_exploracion WHERE codigo IN ('SEV', 'SISMICA'))
            UNION ALL
            SELECT es.id, x.id, 'POSIBLE_DUPLICADO_OTRO_ESTUDIO',
                   'A ' || round(ST_Distance(x.geom, y.geom)::numeric, 1) || ' m de ' || y.codigo || ' (estudio ' || y.estudio_id || ')'
            FROM suelos.exploracion x
            JOIN suelos.estudio_suelos es ON es.id = x.estudio_id
            JOIN suelos.exploracion y ON y.estudio_id <> x.estudio_id
                AND ST_DWithin(x.geom, y.geom, 2)
                AND abs(x.profundidad_total_m - y.profundidad_total_m) < 0.1
            UNION ALL
            SELECT es.id, NULL::bigint, 'ARCHIVO_REPETIDO', 'El mismo PDF (sha256) está en el estudio ' || b.estudio_id
            FROM suelos.adjunto a
            JOIN suelos.adjunto b ON b.sha256 = a.sha256 AND b.estudio_id <> a.estudio_id
            JOIN suelos.estudio_suelos es ON es.id = a.estudio_id;

            CREATE OR REPLACE FUNCTION suelos.fn_suelos_cerca_de_proyecto(p_bpin varchar, p_radio_m numeric DEFAULT 500)
            RETURNS TABLE (
                exploracion_id bigint,
                exploracion varchar,
                estudio varchar,
                entidad text,
                bpin_origen varchar,
                distancia_m numeric,
                franja text,
                tipo_exploracion text,
                profundidad_total_m numeric,
                nivel_freatico_m numeric,
                uscs_predominante varchar,
                uscs_superficial varchar,
                spt_n_min numeric,
                spt_n_max numeric,
                perfil_nsr10 varchar,
                capacidad_portante_adm_kpa numeric,
                fecha_estudio date,
                geom geometry
            )
            LANGUAGE sql
            STABLE
            AS $fn$
                SELECT v.exploracion_id,
                       v.exploracion,
                       v.estudio,
                       v.entidad,
                       v.bpin,
                       round(ST_Distance(zona.geom, ST_Force2D(v.geom))::numeric, 1) AS distancia_m,
                       CASE
                           WHEN ST_Intersects(zona.geom, ST_Force2D(v.geom)) THEN '0. Dentro del polígono'
                           WHEN ST_DWithin(zona.geom, ST_Force2D(v.geom), 100) THEN '1. Hasta 100 m'
                           WHEN ST_DWithin(zona.geom, ST_Force2D(v.geom), 500) THEN '2. 100 a 500 m'
                           ELSE '3. Más de 500 m'
                       END AS franja,
                       v.tipo_exploracion,
                       v.profundidad_total_m,
                       v.nivel_freatico_m,
                       v.uscs_predominante,
                       v.uscs_superficial,
                       v.spt_n_min,
                       v.spt_n_max,
                       v.perfil_nsr10::varchar,
                       v.capacidad_portante_adm_kpa,
                       v.fecha_estudio,
                       v.geom
                FROM public.investment_projects p
                JOIN LATERAL (
                    SELECT a.geom
                    FROM inteligencia.analisis_area a
                    WHERE a.investment_project_id = p.id
                      AND a.geom IS NOT NULL
                    ORDER BY a.fecha DESC, a.id DESC
                    LIMIT 1
                ) zona ON true
                JOIN suelos.v_exploraciones_resumen v
                    ON ST_DWithin(zona.geom, ST_Force2D(v.geom), p_radio_m)
                WHERE p.bpin = p_bpin
                  AND v.visible_en_informe
                ORDER BY distancia_m;
            $fn$;

            DO $grant$
            DECLARE rol text;
            BEGIN
                FOREACH rol IN ARRAY ARRAY['siid_app', 'qgis_editor']
                LOOP
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = rol) THEN
                        EXECUTE format('GRANT USAGE ON SCHEMA suelos TO %I', rol);
                        EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA suelos TO %I', rol);
                        EXECUTE format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA suelos TO %I', rol);
                        EXECUTE format('GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA suelos TO %I', rol);
                        EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA suelos GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', rol);
                        EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA suelos GRANT USAGE, SELECT ON SEQUENCES TO %I', rol);
                    END IF;
                END LOOP;

                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'geoserver_reader') THEN
                    EXECUTE 'GRANT USAGE ON SCHEMA suelos TO geoserver_reader';
                    EXECUTE 'GRANT SELECT ON ALL TABLES IN SCHEMA suelos TO geoserver_reader';
                    EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA suelos GRANT SELECT ON TABLES TO geoserver_reader';
                END IF;
            END
            $grant$;
        SQL);
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            DROP SCHEMA IF EXISTS suelos CASCADE;
            DROP INDEX IF EXISTS public.investment_contracts_id_project_uq;
        SQL);
    }
};
