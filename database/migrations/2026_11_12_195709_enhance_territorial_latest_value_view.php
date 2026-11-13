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

        DB::statement('DROP VIEW IF EXISTS siid_territorial.v_territorial_ultimo_valor');
        DB::statement(<<<'SQL'
            CREATE VIEW siid_territorial.v_territorial_ultimo_valor AS
            WITH ranked AS (
                SELECT s.*,
                       lag(s.valor) OVER (
                           PARTITION BY s.indicador_id, s.territorio_codigo, s.desagregacion_tipo, s.desagregacion_valor
                           ORDER BY s.periodo_fin NULLS LAST, s.periodo_inicio NULLS LAST, s.id
                       ) AS valor_anterior,
                       row_number() OVER (
                           PARTITION BY s.indicador_id, s.territorio_codigo, s.desagregacion_tipo, s.desagregacion_valor
                           ORDER BY s.periodo_fin DESC NULLS LAST, s.periodo_inicio DESC NULLS LAST, s.id DESC
                       ) AS posicion,
                       max(s.valor) FILTER (WHERE s.periodo LIKE '2023%') OVER (
                           PARTITION BY s.indicador_id, s.territorio_codigo, s.desagregacion_tipo, s.desagregacion_valor
                       ) AS valor_2023
                FROM siid_territorial.v_territorial_serie s
            )
            SELECT r.*,
                   i.linea_base_valor,
                   r.valor - r.valor_anterior AS variacion_anterior,
                   r.valor - r.valor_2023 AS variacion_2023,
                   r.valor - i.linea_base_valor AS variacion_linea_base
            FROM ranked r
            JOIN public.indicadores_territoriales i ON i.id = r.indicador_id
            WHERE r.posicion = 1
        SQL);

        $reader = (string) config('database.managed_roles.reader');
        if ($reader !== '' && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $reader)) {
            DB::statement('GRANT SELECT ON siid_territorial.v_territorial_ultimo_valor TO "'.$reader.'"');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP VIEW IF EXISTS siid_territorial.v_territorial_ultimo_valor');
        }
    }
};
