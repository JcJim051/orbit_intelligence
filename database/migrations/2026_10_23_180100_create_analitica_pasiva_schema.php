<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $pgsql = Schema::getConnection()->getDriverName() === 'pgsql';

        if ($pgsql) {
            DB::statement('CREATE SCHEMA IF NOT EXISTS analitica');
        }

        $tabla = $pgsql ? 'analitica.pasiva_rubro' : 'analitica_pasiva_rubro';

        Schema::create($tabla, function (Blueprint $table): void {
            $table->id();
            $table->date('corte');
            $table->string('unidad', 32);
            $table->string('rubro', 160);
            $table->text('concepto');
            $table->string('fuente_codigo', 20);
            $table->string('fuente');
            $table->string('grupo_fuente', 40);
            $table->string('sector');
            $table->string('bpin');
            $table->string('es_inversion', 40);
            $table->decimal('definitiva', 20, 2)->default(0);
            $table->decimal('certificado', 20, 2)->default(0);
            $table->decimal('comprometido', 20, 2)->default(0);
            $table->decimal('obligado', 20, 2)->default(0);
            $table->decimal('pagado', 20, 2)->default(0);
            $table->index('corte');
            $table->index('fuente_codigo');
            $table->index('grupo_fuente');
            $table->index('sector');
        });

        if ($pgsql) {
            DB::statement("COMMENT ON TABLE analitica.pasiva_rubro IS 'Pasiva de Hacienda (informe mensual de ejecución presupuestal de gastos). La carga la hace el SIID; Superset solo lee las vistas.'");
        }

        $this->crearVistas($tabla, $pgsql);
    }

    public function down(): void
    {
        $pgsql = Schema::getConnection()->getDriverName() === 'pgsql';
        $prefijo = $pgsql ? 'analitica.' : 'analitica_';

        foreach (['v_pasiva_fuente_sector', 'v_pasiva_por_sector', 'v_pasiva_por_grupo_fuente'] as $vista) {
            DB::statement('DROP VIEW IF EXISTS '.$prefijo.$vista);
        }

        Schema::dropIfExists($pgsql ? 'analitica.pasiva_rubro' : 'analitica_pasiva_rubro');

        if ($pgsql) {
            DB::statement('DROP SCHEMA IF EXISTS analitica');
        }
    }

    private function crearVistas(string $tabla, bool $pgsql): void
    {
        $prefijo = $pgsql ? 'analitica.' : 'analitica_';

        DB::statement(<<<SQL
            CREATE VIEW {$prefijo}v_pasiva_por_grupo_fuente AS
            SELECT grupo_fuente,
                   SUM(definitiva) AS definitiva,
                   SUM(certificado) AS certificado,
                   SUM(comprometido) AS comprometido,
                   SUM(obligado) AS obligado,
                   SUM(pagado) AS pagado,
                   COUNT(*) AS rubros
            FROM {$tabla}
            GROUP BY grupo_fuente
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$prefijo}v_pasiva_por_sector AS
            SELECT sector,
                   es_inversion,
                   grupo_fuente,
                   SUM(definitiva) AS definitiva,
                   SUM(comprometido) AS comprometido,
                   SUM(obligado) AS obligado,
                   SUM(pagado) AS pagado,
                   COUNT(*) AS rubros
            FROM {$tabla}
            GROUP BY sector, es_inversion, grupo_fuente
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$prefijo}v_pasiva_fuente_sector AS
            SELECT sector,
                   grupo_fuente,
                   fuente_codigo,
                   fuente,
                   SUM(definitiva) AS definitiva,
                   SUM(comprometido) AS comprometido,
                   SUM(obligado) AS obligado,
                   SUM(pagado) AS pagado,
                   COUNT(*) AS rubros
            FROM {$tabla}
            GROUP BY sector, grupo_fuente, fuente_codigo, fuente
        SQL);
    }
};
