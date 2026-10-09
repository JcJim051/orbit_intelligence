<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo de inteligencia geográfica.
 *
 * En SQLite (suite por defecto y desarrollo sin PostGIS) no crea objetos: la
 * geometría, los índices GIST y el SRID 9377 son de PostgreSQL/PostGIS.
 * En el SIID se aplica con la conexión administrativa:
 * php artisan migrate --database=managed_postgis_admin
 *
 * El polígono de análisis vive aquí. investment_projects no tiene geometría;
 * fn_suelos_cerca_de_proyecto usa el análisis más reciente de ese BPIN.
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

            CREATE SCHEMA IF NOT EXISTS inteligencia;

            CREATE TABLE inteligencia.fuente (
                id smallserial PRIMARY KEY,
                codigo varchar(40) NOT NULL UNIQUE,
                nombre varchar(200) NOT NULL,
                activo boolean NOT NULL DEFAULT true,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE TABLE inteligencia.capa (
                id bigserial PRIMARY KEY,
                codigo varchar(60) NOT NULL UNIQUE,
                nombre varchar(200) NOT NULL,
                fuente_id smallint NOT NULL REFERENCES inteligencia.fuente (id) ON DELETE RESTRICT,
                tipo_acceso varchar(20) NOT NULL CHECK (tipo_acceso IN ('rest', 'wfs', 'descarga', 'cargue_propio')),
                url_servicio text,
                layer_id varchar(160),
                endpoints jsonb,
                campos_clave jsonb,
                pregunta text NOT NULL,
                licencia text,
                fecha_actualizacion date,
                activa boolean NOT NULL DEFAULT true,
                observacion text,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE INDEX capa_fuente_ix ON inteligencia.capa (fuente_id);
            CREATE INDEX capa_activa_ix ON inteligencia.capa (activa);

            CREATE TABLE inteligencia.analisis_area (
                id bigserial PRIMARY KEY,
                investment_project_id char(26) REFERENCES public.investment_projects (id) ON DELETE RESTRICT,
                user_id bigint NOT NULL REFERENCES public.users (id) ON DELETE RESTRICT,
                geom geometry(MultiPolygon, 9377) NOT NULL,
                origen_geometria varchar(20) NOT NULL CHECK (origen_geometria IN ('dibujo', 'kmz')),
                nombre_archivo varchar(255),
                fecha timestamptz NOT NULL DEFAULT now(),
                estado varchar(20) NOT NULL DEFAULT 'borrador' CHECK (estado IN ('borrador', 'procesando', 'listo', 'fallido')),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE INDEX analisis_area_geom_gix ON inteligencia.analisis_area USING gist (geom);
            CREATE INDEX analisis_area_proyecto_ix ON inteligencia.analisis_area (investment_project_id);
            CREATE INDEX analisis_area_user_ix ON inteligencia.analisis_area (user_id);
            CREATE INDEX analisis_area_estado_ix ON inteligencia.analisis_area (estado);

            CREATE TABLE inteligencia.analisis_resultado (
                id bigserial PRIMARY KEY,
                analisis_area_id bigint NOT NULL REFERENCES inteligencia.analisis_area (id) ON DELETE CASCADE,
                capa_id bigint NOT NULL REFERENCES inteligencia.capa (id) ON DELETE RESTRICT,
                conteo integer NOT NULL DEFAULT 0 CHECK (conteo >= 0),
                area_m2 numeric(18, 2) CHECK (area_m2 IS NULL OR area_m2 >= 0),
                longitud_m numeric(18, 2) CHECK (longitud_m IS NULL OR longitud_m >= 0),
                resumen jsonb,
                consulted_at timestamptz NOT NULL DEFAULT now(),
                servido_desde varchar(20) NOT NULL CHECK (servido_desde IN ('cache', 'upstream')),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT uq_analisis_resultado_capa UNIQUE (analisis_area_id, capa_id)
            );

            CREATE INDEX analisis_resultado_capa_ix ON inteligencia.analisis_resultado (capa_id);

            CREATE TABLE inteligencia.capa_objeto_cache (
                id bigserial PRIMARY KEY,
                capa_id bigint NOT NULL REFERENCES inteligencia.capa (id) ON DELETE CASCADE,
                geom geometry(Geometry, 9377) NOT NULL,
                atributos jsonb NOT NULL DEFAULT '{}'::jsonb,
                identificador_origen varchar(120),
                fetched_at timestamptz NOT NULL DEFAULT now(),
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now()
            );

            CREATE INDEX capa_objeto_cache_geom_gix ON inteligencia.capa_objeto_cache USING gist (geom);
            CREATE INDEX capa_objeto_cache_capa_ix ON inteligencia.capa_objeto_cache (capa_id);
            CREATE UNIQUE INDEX capa_objeto_cache_origen_uq
                ON inteligencia.capa_objeto_cache (capa_id, identificador_origen)
                WHERE identificador_origen IS NOT NULL;

            CREATE TABLE inteligencia.capa_refresco (
                id bigserial PRIMARY KEY,
                capa_id bigint NOT NULL REFERENCES inteligencia.capa (id) ON DELETE CASCADE,
                iniciado_at timestamptz NOT NULL DEFAULT now(),
                finalizado_at timestamptz,
                estado varchar(20) NOT NULL CHECK (estado IN ('en_proceso', 'exitoso', 'fallido')),
                registros integer CHECK (registros IS NULL OR registros >= 0),
                mensaje text,
                created_at timestamptz NOT NULL DEFAULT now(),
                updated_at timestamptz NOT NULL DEFAULT now(),
                CONSTRAINT ck_capa_refresco_fechas CHECK (finalizado_at IS NULL OR finalizado_at >= iniciado_at)
            );

            CREATE INDEX capa_refresco_capa_ix ON inteligencia.capa_refresco (capa_id, iniciado_at DESC);

            DO $grant$
            DECLARE rol text;
            BEGIN
                FOREACH rol IN ARRAY ARRAY['siid_app', 'qgis_editor']
                LOOP
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = rol) THEN
                        EXECUTE format('GRANT USAGE ON SCHEMA inteligencia TO %I', rol);
                        EXECUTE format('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA inteligencia TO %I', rol);
                        EXECUTE format('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA inteligencia TO %I', rol);
                        EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA inteligencia GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO %I', rol);
                        EXECUTE format('ALTER DEFAULT PRIVILEGES IN SCHEMA inteligencia GRANT USAGE, SELECT ON SEQUENCES TO %I', rol);
                    END IF;
                END LOOP;

                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'geoserver_reader') THEN
                    EXECUTE 'GRANT USAGE ON SCHEMA inteligencia TO geoserver_reader';
                    EXECUTE 'GRANT SELECT ON ALL TABLES IN SCHEMA inteligencia TO geoserver_reader';
                    EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA inteligencia GRANT SELECT ON TABLES TO geoserver_reader';
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

        DB::unprepared('DROP SCHEMA IF EXISTS inteligencia CASCADE');
    }
};
