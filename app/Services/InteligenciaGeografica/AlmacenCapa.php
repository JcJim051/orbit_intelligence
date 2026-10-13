<?php

namespace App\Services\InteligenciaGeografica;

use Illuminate\Support\Facades\DB;

/**
 * Carga temporal de entidades y reemplazo de la copia de una capa en EPSG:9377.
 */
class AlmacenCapa
{
    public function abrir(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS ig_staging');
        DB::unprepared('DROP TABLE IF EXISTS ig_limite');
        DB::unprepared(<<<'SQL'
            CREATE TEMP TABLE ig_staging (
                identificador varchar(120) PRIMARY KEY,
                atributos jsonb NOT NULL,
                geom geometry(Geometry, 9377) NOT NULL
            )
        SQL);
        DB::unprepared('CREATE TEMP TABLE ig_limite (geom geometry(Geometry, 9377) NOT NULL)');
    }

    /**
     * @param  list<array{id: string, atributos: array<string, mixed>, geom: string}>  $entidades
     */
    public function agregar(array $entidades, int $srid): int
    {
        if ($entidades === []) {
            return 0;
        }

        $antes = (int) DB::table('ig_staging')->count();
        $payload = json_encode($entidades, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        DB::affectingStatement(
            'insert into ig_staging (identificador, atributos, geom)
             select id, atributos, geom
             from (
                 select f->>\'id\' as id,
                        coalesce(f->\'atributos\', \'{}\'::jsonb) as atributos,
                        inteligencia.geom_desde_geojson(f->>\'geom\', ?::integer) as geom
                 from jsonb_array_elements(?::jsonb) as f
             ) candidatos
             where id is not null and id <> \'\' and geom is not null
             on conflict (identificador) do update
                set atributos = excluded.atributos,
                    geom = excluded.geom',
            [$srid, $payload],
        );

        return (int) DB::table('ig_staging')->count() - $antes;
    }

    public function agregarLimite(string $geojson, int $srid): bool
    {
        $insertados = DB::affectingStatement(
            'insert into ig_limite (geom)
             select geom
             from (select inteligencia.geom_desde_geojson(?, ?::integer) as geom) candidato
             where geom is not null',
            [$geojson, $srid],
        );

        return $insertados > 0;
    }

    public function aplicarRecorte(): int
    {
        if (DB::table('ig_limite')->count() === 0) {
            return 0;
        }

        return DB::affectingStatement(
            'delete from ig_staging s
             where not exists (
                 select 1 from ig_limite l where ST_Intersects(s.geom, l.geom)
             )',
        );
    }

    public function checksum(): string
    {
        $fila = DB::selectOne(
            "select coalesce(string_agg(
                identificador || '|' || md5(ST_AsEWKB(geom)) || '|' || md5(atributos::text),
                E'\\n' order by identificador
             ), '') as contenido
             from ig_staging",
        );

        return hash('sha256', (string) ($fila->contenido ?? ''));
    }

    public function total(): int
    {
        return (int) DB::table('ig_staging')->count();
    }

    /**
     * @return array{agregadas: int, actualizadas: int, eliminadas: int}
     */
    public function fusionar(int $capaId): array
    {
        $agregadas = (int) DB::selectOne(
            'select count(*) as total
             from ig_staging s
             where not exists (
                 select 1 from inteligencia.capa_objeto_cache c
                 where c.capa_id = ? and c.identificador_origen = s.identificador
             )',
            [$capaId],
        )->total;

        $actualizadas = (int) DB::selectOne(
            'select count(*) as total
             from ig_staging s
             join inteligencia.capa_objeto_cache c
               on c.capa_id = ? and c.identificador_origen = s.identificador
             where c.atributos is distinct from s.atributos
                or not ST_Equals(c.geom, s.geom)',
            [$capaId],
        )->total;

        $eliminadas = (int) DB::selectOne(
            'select count(*) as total
             from inteligencia.capa_objeto_cache c
             where c.capa_id = ?
               and not exists (
                   select 1 from ig_staging s where s.identificador = c.identificador_origen
               )',
            [$capaId],
        )->total;

        DB::affectingStatement(
            'insert into inteligencia.capa_objeto_cache
                (capa_id, geom, atributos, identificador_origen, fetched_at, created_at, updated_at)
             select ?, s.geom, s.atributos, s.identificador, now(), now(), now()
             from ig_staging s
             on conflict (capa_id, identificador_origen) where identificador_origen is not null
             do update set
                geom = excluded.geom,
                atributos = excluded.atributos,
                fetched_at = now(),
                updated_at = now()',
            [$capaId],
        );

        DB::affectingStatement(
            'delete from inteligencia.capa_objeto_cache c
             where c.capa_id = ?
               and not exists (
                   select 1 from ig_staging s where s.identificador = c.identificador_origen
               )',
            [$capaId],
        );

        return [
            'agregadas' => $agregadas,
            'actualizadas' => $actualizadas,
            'eliminadas' => $eliminadas,
        ];
    }

    public function descartar(): void
    {
        DB::unprepared('DROP TABLE IF EXISTS ig_staging');
        DB::unprepared('DROP TABLE IF EXISTS ig_limite');
    }
}
